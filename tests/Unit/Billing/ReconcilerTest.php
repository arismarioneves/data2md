<?php

declare(strict_types=1);

namespace Data2Md\Tests\Unit\Billing;

use Data2Md\Auth\UserStore;
use Data2Md\Billing\BillingStore;
use Data2Md\Billing\CustomerSubscriptionReader;
use Data2Md\Billing\Entitlement;
use Data2Md\Billing\EntitlementWriter;
use Data2Md\Billing\Reconciler;
use Data2Md\Billing\SubscriptionReader;
use Data2Md\Billing\SubscriptionSnapshot;
use Data2Md\Log\Logger;
use Data2Md\Storage\Database;
use PHPUnit\Framework\TestCase;
use Stripe\Exception\ApiConnectionException;

final class ReconcilerTest extends TestCase
{
    private const PRICE = 'price_configured';
    private const NOW   = 1_000_000;

    private string $dbPath;
    private string $logDir;
    private BillingStore $billing;
    private UserStore $users;
    private int $userId;

    protected function setUp(): void
    {
        $this->dbPath = sys_get_temp_dir() . '/d2md-rec-' . bin2hex(random_bytes(6)) . '.sqlite';
        $this->logDir = sys_get_temp_dir() . '/d2md-rec-logs-' . bin2hex(random_bytes(6));
        mkdir($this->logDir, 0750, true);

        $db = new Database($this->dbPath);
        $db->migrate();

        $this->billing = new BillingStore($db);
        $this->users   = new UserStore($db);
        $this->userId  = $this->users->ensure('payer@example.com');
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

    private function reconciler(SubscriptionReader $reader, ?CustomerSubscriptionReader $byCustomer = null): Reconciler
    {
        $logger = new Logger($this->logDir, 10_485_760, false);

        return new Reconciler(
            $reader,
            new EntitlementWriter($this->billing, $logger, self::PRICE),
            $logger,
            $byCustomer,
            $this->billing,
        );
    }

    private function byCustomer(?SubscriptionSnapshot $snapshot): CustomerSubscriptionReader
    {
        return new class ($snapshot) implements CustomerSubscriptionReader {
            public int $calls = 0;

            public function __construct(private readonly ?SubscriptionSnapshot $snapshot)
            {
            }

            public function latestSubscriptionFor(string $customerId): ?SubscriptionSnapshot
            {
                $this->calls++;

                return $this->snapshot;
            }
        };
    }

    private function snapshot(array $overrides = []): SubscriptionSnapshot
    {
        return SubscriptionSnapshot::fromArray([
            'id'                   => 'sub_1',
            'status'               => 'active',
            'customer'             => 'cus_1',
            'cancel_at_period_end' => false,
            'items'                => ['data' => [[
                'price'              => ['id' => self::PRICE],
                'current_period_end' => self::NOW + 86_400,
            ]]],
            ...$overrides,
        ]);
    }

    /** @return array<string,mixed> */
    private function storedUser(array $overrides = []): array
    {
        return [
            ...($this->users->findById($this->userId) ?? []),
            'subscription_id'         => 'sub_1',
            'subscription_status'     => 'active',
            'current_period_end'      => self::NOW + 86_400,
            'subscription_updated_at' => self::NOW - 60,
            ...$overrides,
        ];
    }

    private function logged(): string
    {
        $path = $this->logDir . '/app.jsonl';

        return is_file($path) ? (string) file_get_contents($path) : '';
    }

    public function testAnAccountWithNoSubscriptionIsNotRead(): void
    {
        $reader = $this->reader($this->snapshot());

        self::assertFalse($this->reconciler($reader)->refresh(
            $this->storedUser(['subscription_id' => null]),
            self::NOW
        ));
        self::assertSame(0, $reader->calls);
    }

    /** A recent reading inside a paid period costs nothing to trust. */
    public function testAFreshReadingIsNotTakenAgain(): void
    {
        $reader = $this->reader($this->snapshot());

        self::assertFalse($this->reconciler($reader)->refresh($this->storedUser(), self::NOW));
        self::assertSame(0, $reader->calls, 'every dashboard load must not call Stripe');
    }

    public function testAnOldReadingIsTakenAgain(): void
    {
        $reader = $this->reader($this->snapshot());

        $this->reconciler($reader)->refresh(
            $this->storedUser(['subscription_updated_at' => self::NOW - Reconciler::MAX_AGE_SECONDS]),
            self::NOW
        );

        self::assertSame(1, $reader->calls);
    }

    /** Past the paid period, the stored answer says nothing about now. */
    public function testAnExpiredPeriodIsAlwaysReadAgain(): void
    {
        $reader = $this->reader($this->snapshot());

        $this->reconciler($reader)->refresh(
            $this->storedUser([
                'current_period_end'      => self::NOW - 1,
                'subscription_updated_at' => self::NOW - 1,
            ]),
            self::NOW
        );

        self::assertSame(1, $reader->calls);
    }

    public function testAnAccountNeverReadIsReadNow(): void
    {
        $reader = $this->reader($this->snapshot());

        $this->reconciler($reader)->refresh(
            $this->storedUser(['subscription_updated_at' => null]),
            self::NOW
        );

        self::assertSame(1, $reader->calls);
    }

    /**
     * The reason staleness is a question about time and not about state. A
     * short-circuit on "it already looks active" would never notice a
     * cancellation whose delivery was lost: the stored state says active, so it
     * is never checked, so it stays active for good.
     */
    public function testACancellationWhoseWebhookWasLostIsCaught(): void
    {
        $reader = $this->reader($this->snapshot(['status' => 'canceled']));

        $changed = $this->reconciler($reader)->refresh(
            $this->storedUser(['subscription_updated_at' => self::NOW - 7_200]),
            self::NOW
        );

        self::assertTrue($changed);
        self::assertSame(Entitlement::FREE, Entitlement::forUser($this->users->findById($this->userId)));
    }

    /** A missed delivery is a fault worth an operator seeing. */
    public function testDriftIsLoggedAsAnError(): void
    {
        $reader = $this->reader($this->snapshot(['status' => 'canceled']));

        $this->reconciler($reader)->refresh(
            $this->storedUser(['subscription_updated_at' => self::NOW - 7_200]),
            self::NOW
        );

        self::assertStringContainsString('billing.reconcile.drift', $this->logged());
        self::assertStringContainsString('"lvl":"error"', $this->logged());
    }

    public function testNoDriftIsLoggedWhenTheAnswerAgrees(): void
    {
        $reader = $this->reader($this->snapshot());

        $this->reconciler($reader)->refresh(
            $this->storedUser(['subscription_updated_at' => self::NOW - 7_200]),
            self::NOW
        );

        self::assertStringNotContainsString('billing.reconcile.drift', $this->logged());
    }

    /** A subscriber reading their dashboard must not meet a 500 because Stripe is down. */
    public function testStripeBeingUnreachableLeavesTheAccountAlone(): void
    {
        $reader = $this->reader(null, new ApiConnectionException('network down'));

        $changed = $this->reconciler($reader)->refresh(
            $this->storedUser(['subscription_updated_at' => self::NOW - 7_200]),
            self::NOW
        );

        self::assertFalse($changed);
        self::assertStringContainsString('billing.reconcile.unreachable', $this->logged());
        self::assertNull($this->users->findById($this->userId)['subscription_status'], 'nothing was written');
    }
    /**
     * The worst failure this system has: the customer paid, every delivery for
     * that checkout was lost, and nothing anywhere says so. The customer id is
     * stored before the browser ever leaves for Stripe, so there is always one
     * identifier left to ask with.
     */
    public function testASubscriptionNoDeliveryEverReportedIsRecovered(): void
    {
        $this->users->attachStripeCustomer($this->userId, 'cus_1');

        $byCustomer = $this->byCustomer($this->snapshot());

        $changed = $this->reconciler($this->reader(null), $byCustomer)->refresh(
            $this->storedUser([
                'subscription_id'         => null,
                'subscription_status'     => null,
                'current_period_end'      => null,
                'subscription_updated_at' => null,
                'stripe_customer_id'      => 'cus_1',
            ]),
            self::NOW
        );

        self::assertTrue($changed);
        self::assertSame(1, $byCustomer->calls);
        self::assertSame(Entitlement::PREMIUM, Entitlement::forUser($this->users->findById($this->userId)));
        self::assertStringContainsString('billing.reconcile.recovered', $this->logged());
    }

    /** Every free account with a customer must not call Stripe on each page load. */
    public function testAnAccountAskedAboutRecentlyIsNotAskedAgain(): void
    {
        $this->users->attachStripeCustomer($this->userId, 'cus_1');

        $byCustomer = $this->byCustomer(null);
        $reconciler = $this->reconciler($this->reader(null), $byCustomer);

        $user = $this->storedUser([
            'subscription_id'         => null,
            'subscription_status'     => null,
            'current_period_end'      => null,
            'subscription_updated_at' => null,
            'stripe_customer_id'      => 'cus_1',
        ]);

        self::assertFalse($reconciler->refresh($user, self::NOW));
        self::assertSame(1, $byCustomer->calls);

        // The store now remembers that the answer was nothing.
        self::assertFalse($reconciler->refresh($this->users->findById($this->userId), self::NOW));
        self::assertSame(1, $byCustomer->calls, 'asked once, not once per page load');
    }

    /** An account that never reached checkout has nothing to ask about. */
    public function testAnAccountWithoutACustomerIsNotAskedAbout(): void
    {
        $byCustomer = $this->byCustomer($this->snapshot());

        self::assertFalse($this->reconciler($this->reader(null), $byCustomer)->refresh(
            $this->storedUser([
                'subscription_id'         => null,
                'subscription_updated_at' => null,
                'stripe_customer_id'      => null,
            ]),
            self::NOW
        ));
        self::assertSame(0, $byCustomer->calls);
    }
}
