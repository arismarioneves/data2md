<?php

declare(strict_types=1);

namespace Data2Md\Tests\Unit\Billing;

use Data2Md\Billing\WebhookClaim;
use Data2Md\Billing\WebhookEventStore;
use Data2Md\Storage\Database;
use PHPUnit\Framework\TestCase;

final class WebhookEventStoreTest extends TestCase
{
    private string $dbPath;
    private WebhookEventStore $events;

    protected function setUp(): void
    {
        $this->dbPath = sys_get_temp_dir() . '/d2md-hooks-' . bin2hex(random_bytes(6)) . '.sqlite';

        $db = new Database($this->dbPath);
        $db->migrate();

        $this->events = new WebhookEventStore($db);
    }

    protected function tearDown(): void
    {
        foreach ([$this->dbPath, $this->dbPath . '-wal', $this->dbPath . '-shm'] as $file) {
            if (file_exists($file)) {
                unlink($file);
            }
        }
    }

    public function testFirstSightOfAnEventIsOwned(): void
    {
        self::assertSame(
            WebhookClaim::Owned,
            $this->events->claim('evt_1', 'customer.subscription.updated')
        );
        self::assertSame(1, $this->events->count());
    }

    /** Stripe delivers the same event more than once by design. */
    public function testSecondDeliveryOfAFinishedEventIsAlreadyProcessed(): void
    {
        $this->events->claim('evt_1');
        $this->events->markProcessed('evt_1');

        self::assertSame(WebhookClaim::AlreadyProcessed, $this->events->claim('evt_1'));
        self::assertSame(1, $this->events->count(), 'no second row');
    }

    /**
     * Two workers on one event must not both handle it. The loser has to be
     * told to come back, not told everything is fine.
     */
    public function testConcurrentDeliveryOfTheSameEventIsInFlightForTheLoser(): void
    {
        self::assertSame(WebhookClaim::Owned, $this->events->claim('evt_1', null, 1_000));
        self::assertSame(WebhookClaim::InFlight, $this->events->claim('evt_1', null, 1_000));
    }

    public function testClaimStillHeldInsideTheStaleWindowStaysInFlight(): void
    {
        $this->events->claim('evt_1', null, 1_000);

        $justBefore = 1_000 + WebhookEventStore::STALE_CLAIM_SECONDS - 1;

        self::assertSame(WebhookClaim::InFlight, $this->events->claim('evt_1', null, $justBefore));
    }

    /** A worker killed mid-handler must not hold its event forever. */
    public function testAbandonedClaimIsTakenOver(): void
    {
        $this->events->claim('evt_1', null, 1_000);

        $past = 1_000 + WebhookEventStore::STALE_CLAIM_SECONDS;

        self::assertSame(WebhookClaim::Owned, $this->events->claim('evt_1', null, $past));
    }

    /** Only one of two simultaneous takeovers may win. */
    public function testTakeoverIsExclusive(): void
    {
        $this->events->claim('evt_1', null, 1_000);

        $past = 1_000 + WebhookEventStore::STALE_CLAIM_SECONDS;

        self::assertSame(WebhookClaim::Owned, $this->events->claim('evt_1', null, $past));
        self::assertSame(WebhookClaim::InFlight, $this->events->claim('evt_1', null, $past));
    }

    /** A finished event is never taken over, however old it is. */
    public function testProcessedEventIsNeverTakenOver(): void
    {
        $this->events->claim('evt_1', null, 1_000);
        $this->events->markProcessed('evt_1', 1_010);

        self::assertSame(
            WebhookClaim::AlreadyProcessed,
            $this->events->claim('evt_1', null, 1_000 + 86_400)
        );
    }

    /**
     * Releasing after a failure is what makes Stripe's next attempt work
     * straight away instead of waiting out the stale window.
     */
    public function testReleasingAFailedClaimLetsTheRetryThrough(): void
    {
        $this->events->claim('evt_1', null, 1_000);
        $this->events->release('evt_1');

        self::assertSame(0, $this->events->count());
        self::assertSame(WebhookClaim::Owned, $this->events->claim('evt_1', null, 1_001));
    }

    public function testReleaseCannotUndoAFinishedEvent(): void
    {
        $this->events->claim('evt_1');
        $this->events->markProcessed('evt_1');
        $this->events->release('evt_1');

        self::assertTrue($this->events->isProcessed('evt_1'));
        self::assertSame(WebhookClaim::AlreadyProcessed, $this->events->claim('evt_1'));
    }

    public function testDistinctEventsDoNotInterfere(): void
    {
        self::assertSame(WebhookClaim::Owned, $this->events->claim('evt_1'));
        self::assertSame(WebhookClaim::Owned, $this->events->claim('evt_2'));
        self::assertSame(2, $this->events->count());
    }

    public function testIsProcessedIsFalseWhileClaimed(): void
    {
        $this->events->claim('evt_1');

        self::assertFalse($this->events->isProcessed('evt_1'));
        self::assertFalse($this->events->isProcessed('evt_unknown'));
    }

    public function testPurgeRemovesOnlyOldRows(): void
    {
        $now = 100 * 86_400;

        $this->events->claim('evt_old', null, $now - (31 * 86_400));
        $this->events->claim('evt_new', null, $now - 86_400);

        self::assertSame(1, $this->events->purgeOlderThan(WebhookEventStore::RETENTION_DAYS, $now));
        self::assertSame(1, $this->events->count());
        self::assertSame(WebhookClaim::Owned, $this->events->claim('evt_old', null, $now));
    }

    /** The type is recorded for diagnosis, not used for control flow. */
    public function testEventTypeIsRecorded(): void
    {
        $this->events->claim('evt_1', 'checkout.session.completed');

        $db = new Database($this->dbPath);

        self::assertSame(
            'checkout.session.completed',
            $db->pdo()->query("SELECT type FROM webhook_events WHERE event_id = 'evt_1'")->fetchColumn()
        );
    }
}
