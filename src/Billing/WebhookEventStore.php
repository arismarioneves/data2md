<?php

declare(strict_types=1);

namespace Data2Md\Billing;

use Data2Md\Storage\Database;

/**
 * Which Stripe events have been seen, and which are being handled now.
 *
 * Idempotency by claim rather than by check: "have I seen this?" followed by
 * "record that I have" is two statements with a gap, and Stripe delivers the
 * same event more than once by design. An INSERT that either wins or loses is
 * one statement with no gap.
 */
final class WebhookEventStore
{
    /**
     * A claim left unfinished this long is treated as abandoned. A worker killed
     * mid-handler would otherwise hold its event forever, and Stripe would give
     * up retrying while the app kept answering "in flight".
     */
    public const STALE_CLAIM_SECONDS = 300;

    /** Past Stripe's retry window, with room left to investigate. */
    public const RETENTION_DAYS = 30;

    public function __construct(private readonly Database $db)
    {
    }

    public function claim(string $eventId, ?string $type = null, ?int $now = null): WebhookClaim
    {
        $now = $now ?? time();
        $pdo = $this->db->pdo();

        $insert = $pdo->prepare(
            'INSERT OR IGNORE INTO webhook_events (event_id, type, received_at)
             VALUES (:id, :type, :now)'
        );
        $insert->execute(['id' => $eventId, 'type' => $type, 'now' => $now]);

        if ($insert->rowCount() === 1) {
            return WebhookClaim::Owned;
        }

        $select = $pdo->prepare('SELECT processed_at FROM webhook_events WHERE event_id = :id');
        $select->execute(['id' => $eventId]);

        $row = $select->fetch();

        // Gone between the two statements means a purge ran; treat it as new.
        if ($row === false) {
            return WebhookClaim::Owned;
        }

        if ($row['processed_at'] !== null) {
            return WebhookClaim::AlreadyProcessed;
        }

        // Takeover, expressed as a condition on the row so that two processes
        // attempting it at once cannot both win.
        $takeover = $pdo->prepare(
            'UPDATE webhook_events SET received_at = :now
              WHERE event_id = :id AND processed_at IS NULL AND received_at <= :staleCut'
        );
        $takeover->execute([
            'now'      => $now,
            'id'       => $eventId,
            'staleCut' => $now - self::STALE_CLAIM_SECONDS,
        ]);

        return $takeover->rowCount() === 1 ? WebhookClaim::Owned : WebhookClaim::InFlight;
    }

    public function markProcessed(string $eventId, ?int $now = null): void
    {
        $statement = $this->db->pdo()->prepare(
            'UPDATE webhook_events SET processed_at = :now WHERE event_id = :id'
        );
        $statement->execute(['now' => $now ?? time(), 'id' => $eventId]);
    }

    /**
     * Gives the claim up after a failure, so Stripe's next delivery is handled
     * immediately rather than waiting out STALE_CLAIM_SECONDS.
     */
    public function release(string $eventId): void
    {
        $statement = $this->db->pdo()->prepare(
            'DELETE FROM webhook_events WHERE event_id = :id AND processed_at IS NULL'
        );
        $statement->execute(['id' => $eventId]);
    }

    public function isProcessed(string $eventId): bool
    {
        $statement = $this->db->pdo()->prepare(
            'SELECT processed_at FROM webhook_events WHERE event_id = :id'
        );
        $statement->execute(['id' => $eventId]);

        $row = $statement->fetch();

        return $row !== false && $row['processed_at'] !== null;
    }

    public function purgeOlderThan(int $days, ?int $now = null): int
    {
        $statement = $this->db->pdo()->prepare(
            'DELETE FROM webhook_events WHERE received_at <= :cut'
        );
        $statement->execute(['cut' => ($now ?? time()) - ($days * 86400)]);

        return $statement->rowCount();
    }

    public function count(): int
    {
        return (int) $this->db->pdo()->query('SELECT COUNT(*) FROM webhook_events')->fetchColumn();
    }
}
