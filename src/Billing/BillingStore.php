<?php

declare(strict_types=1);

namespace Data2Md\Billing;

use Data2Md\Storage\Database;

/**
 * The subscription columns of an account.
 *
 * Separate from UserStore, which owns the identity columns, because the two sets
 * are disjoint and a subscription is written by a webhook rather than by anything
 * a person does.
 *
 * There is no cached plan column: entitlement is derived from status and dates
 * by Entitlement, so there is nothing here that can disagree with it.
 */
final class BillingStore
{
    public function __construct(private readonly Database $db)
    {
    }

    /**
     * Writes state read from Stripe.
     *
     * The write is monotonic in read time. Two deliveries handled at once each
     * read fresh state, but the one that read first can still reach the database
     * last, and its answer is the older one. Refusing a write stamped earlier
     * than what is already stored keeps the newest reading.
     *
     * @return bool false when a later reading is already stored
     */
    public function saveSubscription(int $userId, SubscriptionSnapshot $snapshot, int $now): bool
    {
        $statement = $this->db->pdo()->prepare(
            'UPDATE users SET
                stripe_customer_id      = COALESCE(:customer, stripe_customer_id),
                subscription_id         = :subscription,
                subscription_status     = :status,
                subscription_price_id   = :price,
                current_period_end      = :periodEnd,
                cancel_at_period_end    = :cancelling,
                subscription_updated_at = :now
              WHERE id = :id
                AND (subscription_updated_at IS NULL OR subscription_updated_at <= :now)'
        );

        $statement->execute([
            'customer'     => $snapshot->customerId,
            'subscription' => $snapshot->id,
            'status'       => $snapshot->status,
            'price'        => $snapshot->priceId,
            'periodEnd'    => $snapshot->currentPeriodEnd,
            'cancelling'   => $snapshot->cancelAtPeriodEnd ? 1 : 0,
            'now'          => $now,
            'id'           => $userId,
        ]);

        return $statement->rowCount() === 1;
    }

    /**
     * Records that Stripe was asked about this account and had nothing to
     * report, so the question is not asked again on the next page load.
     */
    public function touchRead(int $userId, int $now): void
    {
        $statement = $this->db->pdo()->prepare(
            'UPDATE users SET subscription_updated_at = :now
              WHERE id = :id
                AND (subscription_updated_at IS NULL OR subscription_updated_at <= :now)'
        );
        $statement->execute(['now' => $now, 'id' => $userId]);
    }

    /**
     * How long ago this account's subscription was last read from Stripe, or
     * null when it never has been.
     */
    public function lastReadAt(int $userId): ?int
    {
        $statement = $this->db->pdo()->prepare(
            'SELECT subscription_updated_at FROM users WHERE id = :id'
        );
        $statement->execute(['id' => $userId]);

        $value = $statement->fetchColumn();

        return is_numeric($value) ? (int) $value : null;
    }

    /**
     * Accounts whose stored state is worth re-reading, newest period end first.
     *
     * @return list<array<string,mixed>>
     */
    public function withSubscriptions(int $limit = 500): array
    {
        $statement = $this->db->pdo()->prepare(
            'SELECT * FROM users
              WHERE subscription_id IS NOT NULL
              ORDER BY current_period_end IS NULL DESC, current_period_end ASC
              LIMIT :limit'
        );
        $statement->bindValue('limit', max(1, $limit), \PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAll();
    }
}
