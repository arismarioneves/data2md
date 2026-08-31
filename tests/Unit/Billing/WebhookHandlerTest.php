<?php

declare(strict_types=1);

namespace Data2Md\Tests\Unit\Billing;

use Data2Md\Auth\UserStore;
use Data2Md\Billing\BillingStore;
use Data2Md\Billing\Entitlement;
use Data2Md\Billing\EntitlementWriter;
use Data2Md\Billing\SubscriptionReader;
use Data2Md\Billing\SubscriptionSnapshot;
use Data2Md\Billing\WebhookEventStore;
use Data2Md\Billing\WebhookHandler;
use Data2Md\Log\Logger;
use Data2Md\Storage\Database;
use PHPUnit\Framework\TestCase;
use Stripe\Exception\ApiConnectionException;

final class WebhookHandlerTest extends TestCase
{
    private const PRICE = 'price_123';

    private string $dbPath;
    private string $logDir;
    private Database $db;
    private WebhookEventStore $events;
    private UserStore $users;

    protected function setUp(): void
    {
        $this->dbPath = sys_get_temp_dir() . '/d2md-wh-' . bin2hex(random_bytes(6)) . '.sqlite';
        $this->logDir = sys_get_temp_dir() . '/d2md-wh-logs-' . bin2hex(random_bytes(6));
        mkdir($this->logDir, 0750, true);

        $this->db = new Database($this->dbPath);
        $this->db->migrate();

        $this->events = new WebhookEventStore($this->db);
        $this->users  = new UserStore($this->db);
    }

    protected function tearDown(): void
    {
        foreach ([$this->dbPath, $this->dbPath . '-wal', $this->dbPath . '-shm'] as $file) {
            if (file_exists($file)) {
                unlink($file);
            }
        }

        foreach (glob($this->logDir . '/*') ?: [] as $file) {
            unlink($file);
        }

        if (is_dir($this->logDir)) {
            rmdir($this->logDir);
        }
    }

    private function handler(SubscriptionReader $reader, string $expectedPrice = self::PRICE): WebhookHandler
    {
        // No stderr mirror: the assertions read the file, and a mirrored line
        // per event is noise in the test output.
        $logger = new Logger($this->logDir, 10_485_760, false);

        return new WebhookHandler(
            $this->events,
            $reader,
            $this->users,
            new EntitlementWriter(new BillingStore($this->db), $logger, $expectedPrice),
            $logger,
        );
    }

    /** A reader that answers with whatever Stripe would have said. */
    private function reader(?SubscriptionSnapshot $snapshot, ?\Throwable $throws = null): SubscriptionReader
    {
        return new class ($snapshot, $throws) implements SubscriptionReader {
            public int $calls = 0;

            public function __construct(
                private readonly ?SubscriptionSnapshot $snapshot,
                private readonly ?\Throwable $throws,
            ) {
            }

            public function subscription(string $id): SubscriptionSnapshot
            {
                $this->calls++;

                if ($this->throws !== null) {
                    throw $this->throws;
                }

                return $this->snapshot ?? throw new \LogicException('no snapshot configured');
            }
        };
    }

    private function snapshot(array $overrides = []): SubscriptionSnapshot
    {
        return SubscriptionSnapshot::fromArray([
            'id'                   => 'sub_123',
            'status'               => 'active',
            'customer'             => 'cus_123',
            'cancel_at_period_end' => false,
            'items'                => ['data' => [[
                'price'              => ['id' => self::PRICE],
                'current_period_end' => 2_000_000_000,
            ]]],
            ...$overrides,
        ]);
    }

    /** @return array<string,mixed> */
    private function event(string $type, array $object, string $id = 'evt_1'): array
    {
        return ['id' => $id, 'type' => $type, 'data' => ['object' => $object]];
    }

    private function logged(): string
    {
        $path = $this->logDir . '/app.jsonl';

        return is_file($path) ? (string) file_get_contents($path) : '';
    }

    public function testSubscriptionUpdateForAKnownAccountIsRecorded(): void
    {
        $userId = $this->users->ensure('a@example.com');
        $this->users->attachStripeCustomer($userId, 'cus_123');

        $outcome = $this->handler($this->reader($this->snapshot()))
            ->handle($this->event('customer.subscription.updated', ['id' => 'sub_123']));

        self::assertSame(200, $outcome->status);
        self::assertStringContainsString("user $userId", $outcome->body);
        self::assertTrue($this->events->isProcessed('evt_1'));
    }

    /** Metadata this app set at checkout is the first answer, before any lookup. */
    public function testMetadataUserIdMatchesTheAccount(): void
    {
        $userId = $this->users->ensure('a@example.com');

        $outcome = $this->handler($this->reader($this->snapshot([
            'metadata' => ['user_id' => (string) $userId],
            'customer' => 'cus_unknown',
        ])))->handle($this->event('customer.subscription.created', ['id' => 'sub_123']));

        self::assertSame(200, $outcome->status);
        self::assertStringContainsString("user $userId", $outcome->body);
    }

    /** Stale metadata must not resolve to an account that no longer exists. */
    public function testMetadataForADeletedAccountFallsBackToTheCustomerId(): void
    {
        $kept = $this->users->ensure('kept@example.com');
        $this->users->attachStripeCustomer($kept, 'cus_123');

        $outcome = $this->handler($this->reader($this->snapshot([
            'metadata' => ['user_id' => '9999'],
        ])))->handle($this->event('customer.subscription.updated', ['id' => 'sub_123']));

        self::assertStringContainsString("user $kept", $outcome->body);
    }

    /** Someone paid and there is no account to give it to: acknowledge, but shout. */
    public function testUnmatchedSubscriptionIsAcknowledgedAndLoggedAsAnError(): void
    {
        $outcome = $this->handler($this->reader($this->snapshot()))
            ->handle($this->event('customer.subscription.updated', ['id' => 'sub_123']));

        self::assertSame(200, $outcome->status, 'retrying would reach the same conclusion');
        self::assertStringContainsString('billing.webhook.unmatched', $this->logged());
        self::assertStringContainsString('"lvl":"error"', $this->logged());
        self::assertTrue($this->events->isProcessed('evt_1'));
    }

    /**
     * The payload is not the source of truth. Stripe does not guarantee order,
     * so an update carrying a cancellation can arrive before the creation it
     * followed; taking the newest payload would leave that account premium for
     * good.
     */
    public function testStateComesFromTheApiNotThePayload(): void
    {
        $userId = $this->users->ensure('a@example.com');
        $this->users->attachStripeCustomer($userId, 'cus_123');

        // The payload claims active; Stripe says canceled.
        $reader  = $this->reader($this->snapshot(['status' => 'canceled']));
        $outcome = $this->handler($reader)->handle($this->event(
            'customer.subscription.updated',
            ['id' => 'sub_123', 'status' => 'active', 'cancel_at_period_end' => false]
        ));

        self::assertSame(200, $outcome->status);
        self::assertSame(1, $reader->calls, 'the handler must read back from the API');
        self::assertStringContainsString('"status":"canceled"', $this->logged());
        self::assertStringContainsString('"entitles":false', $this->logged());
    }

    public function testCompletedCheckoutTakesTheSubscriptionFromTheSession(): void
    {
        $userId = $this->users->ensure('a@example.com');
        $this->users->attachStripeCustomer($userId, 'cus_123');

        $reader  = $this->reader($this->snapshot());
        $outcome = $this->handler($reader)->handle($this->event('checkout.session.completed', [
            'id'             => 'cs_1',
            'mode'           => 'subscription',
            'payment_status' => 'paid',
            'subscription'   => 'sub_123',
        ]));

        self::assertSame(200, $outcome->status);
        self::assertSame(1, $reader->calls);
    }

    /**
     * With an asynchronous method enabled, completed fires while the money is
     * still in flight. Acting on it would hand out a free month.
     */
    public function testUnpaidCheckoutIsNotActedOn(): void
    {
        $reader  = $this->reader($this->snapshot());
        $outcome = $this->handler($reader)->handle($this->event('checkout.session.completed', [
            'id'             => 'cs_1',
            'mode'           => 'subscription',
            'payment_status' => 'unpaid',
            'subscription'   => 'sub_123',
        ]));

        self::assertSame(200, $outcome->status);
        self::assertSame(0, $reader->calls, 'nothing may be read for an unpaid session');
        self::assertStringContainsString('"reason":"unpaid"', $this->logged());
    }

    public function testOneOffCheckoutIsNotActedOn(): void
    {
        $reader  = $this->reader($this->snapshot());
        $outcome = $this->handler($reader)->handle($this->event('checkout.session.completed', [
            'id'             => 'cs_1',
            'mode'           => 'payment',
            'payment_status' => 'paid',
        ]));

        self::assertSame(200, $outcome->status);
        self::assertSame(0, $reader->calls);
        self::assertStringContainsString('not_a_subscription', $this->logged());
    }

    public function testUnhandledEventTypeIsAcknowledgedWithoutClaiming(): void
    {
        $outcome = $this->handler($this->reader(null))
            ->handle($this->event('invoice.payment_succeeded', ['id' => 'in_1']));

        self::assertSame(200, $outcome->status);
        self::assertSame(0, $this->events->count(), 'an ignored type needs no record');
    }

    public function testRedeliveryOfAFinishedEventDoesNoWorkTwice(): void
    {
        $userId = $this->users->ensure('a@example.com');
        $this->users->attachStripeCustomer($userId, 'cus_123');

        $reader  = $this->reader($this->snapshot());
        $handler = $this->handler($reader);
        $event   = $this->event('customer.subscription.updated', ['id' => 'sub_123']);

        self::assertSame(200, $handler->handle($event)->status);
        self::assertSame(200, $handler->handle($event)->status);
        self::assertSame(1, $reader->calls, 'the second delivery must not re-read Stripe');
    }

    /** Stripe being unreachable is temporary, so the event has to come back. */
    public function testStripeFailureAsksForARetryAndFreesTheClaim(): void
    {
        $outcome = $this->handler($this->reader(null, new ApiConnectionException('network down')))
            ->handle($this->event('customer.subscription.updated', ['id' => 'sub_123']));

        self::assertSame(503, $outcome->status);
        self::assertSame('30', $outcome->headers['Retry-After'] ?? null);
        self::assertSame(0, $this->events->count(), 'the claim is released for the retry');
        self::assertStringContainsString('billing.webhook.api_fail', $this->logged());
    }

    public function testUnexpectedFailureIsAFiveHundredAndFreesTheClaim(): void
    {
        $outcome = $this->handler($this->reader(null, new \RuntimeException('boom')))
            ->handle($this->event('customer.subscription.updated', ['id' => 'sub_123']));

        self::assertSame(500, $outcome->status);
        self::assertSame(0, $this->events->count());
        self::assertStringContainsString('billing.webhook.fail', $this->logged());
    }

    public function testAnEventBeingHandledElsewhereIsAskedToComeBack(): void
    {
        $this->events->claim('evt_1', 'customer.subscription.updated');

        $outcome = $this->handler($this->reader($this->snapshot()))
            ->handle($this->event('customer.subscription.updated', ['id' => 'sub_123']));

        self::assertSame(503, $outcome->status, 'a 2xx here would lose the event if the holder dies');
        self::assertArrayHasKey('Retry-After', $outcome->headers);
    }

    public function testEventWithoutAnIdIsRejected(): void
    {
        $outcome = $this->handler($this->reader(null))
            ->handle(['type' => 'customer.subscription.updated', 'data' => ['object' => []]]);

        self::assertSame(500, $outcome->status);
    }

    /** app.jsonl mirrors to stderr and into the host's log viewer. */
    public function testNoEmailAddressReachesTheApplicationLog(): void
    {
        $userId = $this->users->ensure('private@example.com');
        $this->users->attachStripeCustomer($userId, 'cus_123');

        $this->handler($this->reader($this->snapshot()))
            ->handle($this->event('customer.subscription.updated', ['id' => 'sub_123']));

        self::assertStringNotContainsString('private@example.com', $this->logged());
        self::assertStringContainsString('"user":' . $userId, $this->logged());
    }

    public function testAnActiveSubscriptionReachesTheAccount(): void
    {
        $userId = $this->users->ensure('a@example.com');
        $this->users->attachStripeCustomer($userId, 'cus_123');

        $outcome = $this->handler($this->reader($this->snapshot()))
            ->handle($this->event('customer.subscription.updated', ['id' => 'sub_123']));

        self::assertStringStartsWith('applied', $outcome->body);

        $user = $this->users->findById($userId);

        self::assertSame('active', $user['subscription_status']);
        self::assertSame('sub_123', $user['subscription_id']);
        self::assertSame(2_000_000_000, (int) $user['current_period_end']);
        self::assertSame(Entitlement::PREMIUM, Entitlement::forUser($user));
    }

    public function testACancellationRemovesEntitlement(): void
    {
        $userId = $this->users->ensure('a@example.com');
        $this->users->attachStripeCustomer($userId, 'cus_123');

        $this->handler($this->reader($this->snapshot()))
            ->handle($this->event('customer.subscription.updated', ['id' => 'sub_123'], 'evt_1'));

        $this->handler($this->reader($this->snapshot(['status' => 'canceled'])))
            ->handle($this->event('customer.subscription.deleted', ['id' => 'sub_123'], 'evt_2'));

        self::assertSame(Entitlement::FREE, Entitlement::forUser($this->users->findById($userId)));
    }

    /** An account with no subscription of ours must not be granted one. */
    public function testAPriceThisDeploymentDoesNotSellChangesNothing(): void
    {
        $userId = $this->users->ensure('a@example.com');
        $this->users->attachStripeCustomer($userId, 'cus_123');

        $outcome = $this->handler($this->reader($this->snapshot()), 'price_something_else')
            ->handle($this->event('customer.subscription.updated', ['id' => 'sub_123']));

        self::assertStringStartsWith('recorded', $outcome->body);
        self::assertSame(Entitlement::FREE, Entitlement::forUser($this->users->findById($userId)));
        self::assertStringContainsString('billing.price_mismatch', $this->logged());
    }
}
