<?php

declare(strict_types=1);

namespace Data2Md\Tests\Unit\Billing;

use Data2Md\Auth\UserStore;
use Data2Md\Billing\BillingStore;
use Data2Md\Billing\Entitlement;
use Data2Md\Billing\EntitlementWriter;
use Data2Md\Billing\SubscriptionSnapshot;
use Data2Md\Log\Logger;
use Data2Md\Storage\Database;
use PHPUnit\Framework\TestCase;

final class EntitlementWriterTest extends TestCase
{
    private const PRICE = 'price_configured';

    private string $dbPath;
    private string $logDir;
    private BillingStore $billing;
    private UserStore $users;
    private int $userId;

    protected function setUp(): void
    {
        $this->dbPath = sys_get_temp_dir() . '/d2md-ent-' . bin2hex(random_bytes(6)) . '.sqlite';
        $this->logDir = sys_get_temp_dir() . '/d2md-ent-logs-' . bin2hex(random_bytes(6));
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

    private function writer(string $expectedPrice = self::PRICE): EntitlementWriter
    {
        return new EntitlementWriter(
            $this->billing,
            new Logger($this->logDir, 10_485_760, false),
            $expectedPrice
        );
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
                'current_period_end' => 2_000,
            ]]],
            ...$overrides,
        ]);
    }

    /** @return array<string,mixed> */
    private function user(): array
    {
        return $this->users->findById($this->userId) ?? self::fail('the account vanished');
    }

    private function logged(): string
    {
        $path = $this->logDir . '/app.jsonl';

        return is_file($path) ? (string) file_get_contents($path) : '';
    }

    public function testAnActiveSubscriptionMakesTheAccountPremium(): void
    {
        self::assertTrue($this->writer()->apply($this->user(), $this->snapshot(), 1_000));

        $user = $this->user();

        self::assertSame('active', $user['subscription_status']);
        self::assertSame('sub_1', $user['subscription_id']);
        self::assertSame(self::PRICE, $user['subscription_price_id']);
        self::assertSame(2_000, (int) $user['current_period_end']);
        self::assertSame(0, (int) $user['cancel_at_period_end']);
        self::assertSame(Entitlement::PREMIUM, Entitlement::forUser($user));
    }

    /** The customer id has to be stored, or the next event has nothing to match on. */
    public function testTheCustomerIdIsRecorded(): void
    {
        $this->writer()->apply($this->user(), $this->snapshot(), 1_000);

        self::assertSame('cus_1', $this->user()['stripe_customer_id']);
    }

    /** A reading without a customer must not erase the one already stored. */
    public function testAnAbsentCustomerIdDoesNotEraseTheStoredOne(): void
    {
        $this->writer()->apply($this->user(), $this->snapshot(), 1_000);
        $this->writer()->apply($this->user(), $this->snapshot(['customer' => null]), 1_100);

        self::assertSame('cus_1', $this->user()['stripe_customer_id']);
    }

    public function testCancellationTakesPremiumAway(): void
    {
        $this->writer()->apply($this->user(), $this->snapshot(), 1_000);
        $this->writer()->apply($this->user(), $this->snapshot(['status' => 'canceled']), 1_100);

        self::assertSame(Entitlement::FREE, Entitlement::forUser($this->user()));
    }

    /** They paid for the rest of the period, so they keep it. */
    public function testCancellingAtPeriodEndStaysPremium(): void
    {
        $this->writer()->apply(
            $this->user(),
            $this->snapshot(['cancel_at_period_end' => true]),
            1_000
        );

        $user = $this->user();

        self::assertSame(1, (int) $user['cancel_at_period_end']);
        self::assertSame(Entitlement::PREMIUM, Entitlement::forUser($user));
    }

    /** Anything Stripe invents later is outside premium by construction. */
    public function testAnUnknownStatusIsNotEntitling(): void
    {
        $this->writer()->apply($this->user(), $this->snapshot(['status' => 'something_new']), 1_000);

        self::assertSame('something_new', $this->user()['subscription_status']);
        self::assertSame(Entitlement::FREE, Entitlement::forUser($this->user()));
    }

    public function testTrialingIsEntitling(): void
    {
        $this->writer()->apply($this->user(), $this->snapshot(['status' => 'trialing']), 1_000);

        self::assertSame(Entitlement::PREMIUM, Entitlement::forUser($this->user()));
    }

    /**
     * The delivery-order case. An update carrying the cancellation can reach the
     * database after the creation that followed it; keeping the newest reading
     * is what stops the account being premium for good.
     */
    public function testAnOlderReadingCannotOverwriteANewerOne(): void
    {
        self::assertTrue($this->writer()->apply($this->user(), $this->snapshot(['status' => 'canceled']), 2_000));

        $stale = $this->user();
        $stale['subscription_updated_at'] = 2_000;

        self::assertFalse(
            $this->writer()->apply($stale, $this->snapshot(['status' => 'active']), 1_000),
            'a reading taken earlier must not win'
        );
        self::assertSame('canceled', $this->user()['subscription_status']);
        self::assertStringContainsString('billing.apply.superseded', $this->logged());
    }

    public function testAReadingAtTheSameInstantIsAccepted(): void
    {
        $this->writer()->apply($this->user(), $this->snapshot(['status' => 'past_due']), 2_000);

        self::assertTrue($this->writer()->apply($this->user(), $this->snapshot(), 2_000));
        self::assertSame('active', $this->user()['subscription_status']);
    }

    /**
     * Another product in the same Stripe account. Guessing what its price
     * entitles is how a customer of one product loses access to another.
     */
    public function testAnotherProductsPriceChangesNothing(): void
    {
        self::assertFalse($this->writer()->apply(
            $this->user(),
            $this->snapshot(['items' => ['data' => [['price' => ['id' => 'price_other']]]]]),
            1_000
        ));

        self::assertNull($this->user()['subscription_status']);
        self::assertStringContainsString('billing.price_mismatch', $this->logged());
    }

    /** An unconfigured deployment must not grant anything. */
    public function testNothingIsAppliedWithoutAConfiguredPrice(): void
    {
        self::assertFalse($this->writer('')->apply($this->user(), $this->snapshot(), 1_000));
        self::assertNull($this->user()['subscription_status']);
    }

    /**
     * A second subscription reporting something negative is not evidence that
     * this account's own subscription ended.
     */
    public function testAForeignSubscriptionCannotDowngrade(): void
    {
        $this->writer()->apply($this->user(), $this->snapshot(), 1_000);

        self::assertFalse($this->writer()->apply(
            $this->user(),
            $this->snapshot(['id' => 'sub_other', 'status' => 'canceled']),
            1_100
        ));

        self::assertSame(Entitlement::PREMIUM, Entitlement::forUser($this->user()));
        self::assertStringContainsString('billing.subscription.foreign', $this->logged());
    }

    /** A replacement that is active is a real upgrade and may take over. */
    public function testAForeignSubscriptionThatIsActiveTakesOver(): void
    {
        $this->writer()->apply($this->user(), $this->snapshot(['status' => 'canceled']), 1_000);

        self::assertTrue($this->writer()->apply(
            $this->user(),
            $this->snapshot(['id' => 'sub_new']),
            1_100
        ));

        self::assertSame('sub_new', $this->user()['subscription_id']);
        self::assertSame(Entitlement::PREMIUM, Entitlement::forUser($this->user()));
    }

    /** app.jsonl mirrors to stderr and into the host's log viewer. */
    public function testNoEmailReachesTheApplicationLog(): void
    {
        $this->writer()->apply($this->user(), $this->snapshot(), 1_000);

        self::assertStringNotContainsString('payer@example.com', $this->logged());
        self::assertStringContainsString('billing.apply', $this->logged());
    }
}
