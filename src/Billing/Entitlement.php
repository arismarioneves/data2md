<?php

declare(strict_types=1);

namespace Data2Md\Billing;

/**
 * Which plan an account is on, derived rather than stored.
 *
 * A cached plan column would be a second source of truth next to the
 * subscription state, and the two drift the first time a webhook writes one and
 * not the other.
 */
final class Entitlement
{
    public const ANON    = 'anon';
    public const FREE    = 'free';
    public const PREMIUM = 'premium';

    /**
     * An allowlist, not a denylist: any status Stripe invents later falls
     * outside premium by construction rather than granting it by accident.
     *
     * cancel_at_period_end with status active is still active — the visitor
     * paid for the rest of the period.
     */
    private const ACTIVE_STATUSES = ['active', 'trialing'];

    /** @param array<string,mixed>|null $user */
    public static function forUser(?array $user, ?int $now = null): string
    {
        if ($user === null) {
            return self::ANON;
        }

        $now = $now ?? time();

        // Set by hand for a comped account; independent of Stripe.
        $until = $user['premium_until'] ?? null;

        if ($until !== null && (int) $until > $now) {
            return self::PREMIUM;
        }

        $status = $user['subscription_status'] ?? null;

        if (is_string($status) && in_array($status, self::ACTIVE_STATUSES, true)) {
            return self::PREMIUM;
        }

        return self::FREE;
    }

    /** @param array<string,mixed>|null $user */
    public static function isPremium(?array $user, ?int $now = null): bool
    {
        return self::forUser($user, $now) === self::PREMIUM;
    }
}
