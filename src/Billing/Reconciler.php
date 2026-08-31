<?php

declare(strict_types=1);

namespace Data2Md\Billing;

use Data2Md\Log\Logger;
use Stripe\Exception\ApiErrorException;

/**
 * Re-reads a subscription from Stripe when the stored copy may have gone stale.
 *
 * Webhooks are the fast path and this is the safety net. A delivery can be lost
 * for good -- an endpoint disabled after repeated failures, a deploy during the
 * retry window -- and without a second reader the account keeps whatever it had
 * when the last delivery arrived.
 */
final class Reconciler
{
    /** How long a reading is trusted before it is worth taking again. */
    public const MAX_AGE_SECONDS = 3600;

    public function __construct(
        private readonly SubscriptionReader $stripe,
        private readonly EntitlementWriter $entitlement,
        private readonly Logger $logger,
        /** Optional: without it, an account with no subscription recorded is skipped. */
        private readonly ?CustomerSubscriptionReader $byCustomer = null,
        private readonly ?BillingStore $store = null,
    ) {
    }

    /**
     * @param array<string,mixed> $user
     * @return bool true when the account was rewritten
     */
    public function refresh(array $user, ?int $now = null): bool
    {
        $now            = $now ?? time();
        $subscriptionId = $user['subscription_id'] ?? null;

        if (!$this->isStale($user, $now)) {
            return false;
        }

        if (!is_string($subscriptionId) || $subscriptionId === '') {
            return $this->recover($user, $now);
        }

        try {
            $snapshot = $this->stripe->subscription($subscriptionId);
        } catch (ApiErrorException $e) {
            // Stripe being unreachable must never turn into a 500 on a page the
            // subscriber came to read. The stored state stands until next time.
            $this->logger->app('billing.reconcile.unreachable', [
                'user'   => (int) $user['id'],
                'detail' => $e->getMessage(),
            ], 'warning');

            return false;
        }

        $wasEntitled = Entitlement::isEntitlingStatus($user['subscription_status'] ?? null);

        if ($wasEntitled !== $snapshot->isEntitling()) {
            // The stored answer was wrong, which means a webhook never arrived.
            // Loud: one missed delivery is a fault, and a pattern of them is an
            // endpoint Stripe is about to disable.
            $this->logger->app('billing.reconcile.drift', [
                'user'         => (int) $user['id'],
                'subscription' => $snapshot->id,
                'stored'       => $user['subscription_status'] ?? null,
                'actual'       => $snapshot->status,
            ], 'error');
        }

        return $this->entitlement->apply($user, $snapshot, $now);
    }

    /**
     * Looks for a subscription this app never heard about.
     *
     * The case is an account that paid and whose deliveries were all lost: it
     * has a customer, because one is created before checkout, but nothing else.
     * Without this the money is gone and nothing anywhere says so.
     *
     * @param array<string,mixed> $user
     */
    private function recover(array $user, int $now): bool
    {
        $customerId = $user['stripe_customer_id'] ?? null;

        if ($this->byCustomer === null || !is_string($customerId) || $customerId === '') {
            return false;
        }

        try {
            $snapshot = $this->byCustomer->latestSubscriptionFor($customerId);
        } catch (ApiErrorException $e) {
            $this->logger->app('billing.reconcile.unreachable', [
                'user'   => (int) $user['id'],
                'detail' => $e->getMessage(),
            ], 'warning');

            return false;
        }

        if ($snapshot === null) {
            // Asked and told nothing, so do not ask again on the next page load.
            $this->store?->touchRead((int) $user['id'], $now);

            return false;
        }

        $this->logger->app('billing.reconcile.recovered', [
            'user'         => (int) $user['id'],
            'subscription' => $snapshot->id,
            'status'       => $snapshot->status,
        ], 'error');

        return $this->entitlement->apply($user, $snapshot, $now);
    }

    /**
     * Staleness is a question about time, never about state.
     *
     * "It already has an active subscription, so skip" would never notice a
     * cancellation whose webhook was lost: the stored state says active, so it
     * is never checked, so it stays active for good. Both halves are about
     * clocks -- the paid period has run out, or the reading is simply old.
     *
     * @param array<string,mixed> $user
     */
    private function isStale(array $user, int $now): bool
    {
        $periodEnd = $user['current_period_end'] ?? null;
        $readAt    = $user['subscription_updated_at'] ?? null;

        // Never read, or read before this app started recording when.
        if (!is_numeric($readAt)) {
            return true;
        }

        if ((int) $readAt <= $now - self::MAX_AGE_SECONDS) {
            return true;
        }

        // A period that has run out makes even a recent reading say nothing
        // about now. An account with no period at all has no subscription to
        // have an opinion about, which is not the same as an expired one.
        return is_numeric($periodEnd) && (int) $periodEnd <= $now;
    }
}
