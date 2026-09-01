<?php

declare(strict_types=1);

namespace Data2Md\Billing;

use Data2Md\Log\Logger;

/**
 * Decides whether a subscription reading may change an account, and records it.
 *
 * The rule throughout is that only a definite negative signal from Stripe takes
 * access away. Anything this app is unsure about leaves the account as it is: a
 * subscriber wrongly downgraded is a paying customer locked out, while a
 * cancelled subscriber left premium for one more reconcile costs a few cents.
 */
final class EntitlementWriter
{
    public function __construct(
        private readonly BillingStore $store,
        private readonly Logger $logger,
        /** Empty means billing is not configured, so nothing may be applied. */
        private readonly string $expectedPriceId,
    ) {
    }

    /**
     * @param array<string,mixed> $user the account as currently stored
     * @return bool true when the account was written
     */
    public function apply(array $user, SubscriptionSnapshot $snapshot, ?int $now = null): bool
    {
        $now    = $now ?? time();
        $userId = (int) $user['id'];

        // The price is metadata, never a condition on entitlement. A price this
        // deployment does not know about means someone bought something else in
        // the same Stripe account, and guessing what it entitles is how a
        // customer of one product loses access to another.
        if ($this->expectedPriceId === '' || $snapshot->priceId !== $this->expectedPriceId) {
            $this->logger->app('billing.price_mismatch', [
                'user'         => $userId,
                'subscription' => $snapshot->id,
                'price'        => $snapshot->priceId,
                'expected'     => $this->expectedPriceId === '' ? null : $this->expectedPriceId,
            ], 'warning');

            return false;
        }

        $stored = $user['subscription_id'] ?? null;

        // A different subscription reporting something negative is not evidence
        // that this account's own subscription ended. Only its own can end it.
        if (is_string($stored) && $stored !== '' && $stored !== $snapshot->id && !$snapshot->isEntitling()) {
            $this->logger->app('billing.subscription.foreign', [
                'user'         => $userId,
                'stored'       => $stored,
                'subscription' => $snapshot->id,
                'status'       => $snapshot->status,
            ], 'warning');

            return false;
        }

        $written = $this->store->saveSubscription($userId, $snapshot, $now);

        if (!$written) {
            // The stored reading is newer than this one, which happens when two
            // deliveries are handled at once.
            $this->logger->app('billing.apply.superseded', [
                'user'         => $userId,
                'subscription' => $snapshot->id,
            ]);

            return false;
        }

        $this->logger->app('billing.apply', [
            'user'         => $userId,
            'subscription' => $snapshot->id,
            'status'       => $snapshot->status,
            'entitles'     => $snapshot->isEntitling(),
            'period_end'   => $snapshot->currentPeriodEnd,
            'cancelling'   => $snapshot->cancelAtPeriodEnd,
        ]);

        return true;
    }
}
