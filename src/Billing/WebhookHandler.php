<?php

declare(strict_types=1);

namespace Data2Md\Billing;

use Data2Md\Auth\UserStore;
use Data2Md\Log\Logger;
use Stripe\Exception\ApiErrorException;

/**
 * What one verified Stripe event means for this app.
 *
 * Knows nothing about HTTP beyond the status to answer with, so the whole of it
 * is reachable from a test without a running Stripe.
 *
 * This is the record-only stage: it claims the event, reads the subscription
 * back from the API and says which account it belongs to, and writes no
 * entitlement. Delivery, signatures and matching get proven before anything
 * here can grant or remove access.
 */
final class WebhookHandler
{
    /**
     * Anything else is acknowledged and dropped. Stripe sends what the endpoint
     * is subscribed to, and an endpoint subscribed too widely should not turn
     * into retries for events this app has no opinion about.
     *
     * @var list<string>
     */
    private const HANDLED_TYPES = [
        'checkout.session.completed',
        'customer.subscription.created',
        'customer.subscription.updated',
        'customer.subscription.deleted',
    ];

    private const RETRY_AFTER_SECONDS = 30;

    public function __construct(
        private readonly WebhookEventStore $events,
        private readonly SubscriptionReader $stripe,
        private readonly UserStore $users,
        private readonly Logger $logger,
    ) {
    }

    /** @param array<string,mixed> $event a signature-verified Stripe event */
    public function handle(array $event, ?int $now = null): WebhookOutcome
    {
        $eventId = (string) ($event['id'] ?? '');
        $type    = (string) ($event['type'] ?? '');

        if ($eventId === '') {
            return WebhookOutcome::failed("event has no id\n");
        }

        $this->logger->app('billing.webhook.received', ['event' => $eventId, 'type' => $type]);

        if (!in_array($type, self::HANDLED_TYPES, true)) {
            $this->logger->app('billing.webhook.ignored', ['event' => $eventId, 'type' => $type]);

            return WebhookOutcome::ok("ignored $type\n");
        }

        $claim = $this->events->claim($eventId, $type, $now);

        if ($claim === WebhookClaim::AlreadyProcessed) {
            return WebhookOutcome::ok("already processed $eventId\n");
        }

        if ($claim === WebhookClaim::InFlight) {
            // Not a 2xx: another worker holds it, and if that worker dies the
            // event still has to come back.
            return WebhookOutcome::retry("$eventId is being handled\n", self::RETRY_AFTER_SECONDS);
        }

        try {
            return $this->process($eventId, $type, $event);
        } catch (ApiErrorException $e) {
            // Stripe unreachable is temporary, so give the claim up rather than
            // leave the retry waiting out the stale window.
            $this->events->release($eventId);
            $this->logger->app('billing.webhook.api_fail', [
                'event'  => $eventId,
                'detail' => $e->getMessage(),
            ], 'error');

            return WebhookOutcome::retry("could not reach Stripe\n", self::RETRY_AFTER_SECONDS);
        } catch (\Throwable $e) {
            $this->events->release($eventId);
            $this->logger->app('billing.webhook.fail', [
                'event'  => $eventId,
                'reason' => $e::class,
                'detail' => $e->getMessage(),
            ], 'error');

            return WebhookOutcome::failed("handler failed\n");
        }
    }

    /** @param array<string,mixed> $event */
    private function process(string $eventId, string $type, array $event): WebhookOutcome
    {
        $object = $event['data']['object'] ?? [];
        $object = is_array($object) ? $object : [];

        $subscriptionId = $this->subscriptionIdFrom($type, $object, $eventId);

        if ($subscriptionId === null) {
            // Nothing to read means nothing to do, and re-delivering it would
            // reach the same conclusion.
            $this->events->markProcessed($eventId);

            return WebhookOutcome::ok("nothing to apply for $eventId\n");
        }

        $snapshot = $this->stripe->subscription($subscriptionId);
        $userId   = $this->resolveUserId($snapshot);

        if ($userId === null) {
            // A subscription created outside this app's checkout, so there is no
            // account to attach it to. Loud, because it means someone paid and
            // got nothing.
            $this->logger->app('billing.webhook.unmatched', [
                'event'        => $eventId,
                'subscription' => $snapshot->id,
                'customer'     => $snapshot->customerId,
            ], 'error');

            $this->events->markProcessed($eventId);

            return WebhookOutcome::ok("no account matches $subscriptionId\n");
        }

        $this->logger->app('billing.webhook.resolved', [
            'event'        => $eventId,
            'type'         => $type,
            'subscription' => $snapshot->id,
            'status'       => $snapshot->status,
            'entitles'     => $snapshot->isEntitling(),
            'period_end'   => $snapshot->currentPeriodEnd,
            'cancelling'   => $snapshot->cancelAtPeriodEnd,
            'price'        => $snapshot->priceId,
            'user'         => $userId,
        ]);

        $this->events->markProcessed($eventId);

        return WebhookOutcome::ok("recorded $eventId for user $userId\n");
    }

    /**
     * The subscription an event is about, or null when the event carries none to
     * act on.
     *
     * @param array<string,mixed> $object
     */
    private function subscriptionIdFrom(string $type, array $object, string $eventId): ?string
    {
        if ($type !== 'checkout.session.completed') {
            $id = $object['id'] ?? null;

            return is_string($id) && $id !== '' ? $id : null;
        }

        if (($object['mode'] ?? '') !== 'subscription') {
            $this->logger->app('billing.webhook.skipped', [
                'event'  => $eventId,
                'reason' => 'not_a_subscription',
            ]);

            return null;
        }

        // With any asynchronous method enabled, completed fires while the money
        // is still in flight. Granting on that hands out a free month, so the
        // subscription events are what this app waits for instead.
        if (($object['payment_status'] ?? '') !== 'paid') {
            $this->logger->app('billing.webhook.skipped', [
                'event'  => $eventId,
                'reason' => 'unpaid',
                'status' => $object['payment_status'] ?? null,
            ], 'warning');

            return null;
        }

        $subscription = $object['subscription'] ?? null;

        if (is_array($subscription)) {
            $subscription = $subscription['id'] ?? null;
        }

        return is_string($subscription) && $subscription !== '' ? $subscription : null;
    }

    /**
     * Which account a subscription belongs to.
     *
     * Never by e-mail: the address on a Checkout Session is whatever the payer
     * typed, so matching on it lets one customer's payment land on another
     * customer's account. Metadata this app set, or the customer id it stored,
     * are the only two answers.
     */
    private function resolveUserId(SubscriptionSnapshot $snapshot): ?int
    {
        if ($snapshot->userId !== null && $this->users->findById($snapshot->userId) !== null) {
            return $snapshot->userId;
        }

        if ($snapshot->customerId !== null) {
            $user = $this->users->findByStripeCustomer($snapshot->customerId);

            if ($user !== null) {
                return (int) $user['id'];
            }
        }

        return null;
    }
}
