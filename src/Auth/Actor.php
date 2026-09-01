<?php

declare(strict_types=1);

namespace Data2Md\Auth;

use Data2Md\Billing\Entitlement;

/**
 * Who is asking for a conversion, and what that entitles them to.
 *
 * Bucket keys are namespaced by endpoint and by identity kind. Sharing one
 * bucket between endpoints would let a download loop spend an upload's
 * allowance; sharing one between an address and an account would mean a signed
 * in visitor inherits whatever an earlier anonymous one used up.
 */
final class Actor
{
    private const CHANNEL_BROWSER = 'convert';
    private const CHANNEL_API     = 'api';

    private function __construct(
        public readonly ?int $userId,
        public readonly string $plan,
        public readonly Tier $tier,
        public readonly string $ip,
        private readonly int $freeIpWindowSeconds,
        private readonly string $channel = self::CHANNEL_BROWSER,
    ) {
    }

    public static function anonymous(string $ip, TierResolver $tiers): self
    {
        return new self(null, Entitlement::ANON, $tiers->for(Entitlement::ANON), $ip, 0);
    }

    /** @param array<string,mixed> $user */
    public static function forUser(array $user, string $ip, TierResolver $tiers, ?int $now = null): self
    {
        $plan = Entitlement::forUser($user, $now);

        return new self(
            (int) $user['id'],
            $plan,
            $tiers->for($plan),
            $ip,
            $plan === Entitlement::FREE ? $tiers->freeIpWindowSeconds() : 0,
        );
    }

    /**
     * A token holder, counted separately from the same account's browsing so
     * that using the site does not spend the integration's allowance.
     *
     * No per-address floor: the API is subscriber-only, and a subscription is
     * the attribution the floor exists to substitute for.
     *
     * @param array<string,mixed> $user
     */
    public static function forApi(array $user, string $ip, TierResolver $tiers, ?int $now = null): self
    {
        $plan = Entitlement::forUser($user, $now);

        return new self((int) $user['id'], $plan, $tiers->for($plan), $ip, 0, self::CHANNEL_API);
    }

    public function isAuthenticated(): bool
    {
        return $this->userId !== null;
    }

    /**
     * Every bucket this conversion must satisfy, in the order they are charged.
     *
     * @return list<QuotaCheck>
     */
    public function quotaChecks(): array
    {
        if ($this->userId === null) {
            return [new QuotaCheck(
                'convert:anon:' . $this->ip,
                $this->tier->maxConversions,
                $this->tier->windowSeconds,
            )];
        }

        $checks = [new QuotaCheck(
            $this->channel . ':user:' . $this->userId,
            $this->tier->maxConversions,
            $this->tier->windowSeconds,
        )];

        // Paid callers are exempt: the subscription is the attribution.
        if ($this->freeIpWindowSeconds > 0) {
            $checks[] = new QuotaCheck(
                'convert:free-ip:' . $this->ip,
                1,
                $this->freeIpWindowSeconds,
            );
        }

        return $checks;
    }

    /**
     * The bucket a caller is measured against for reporting, which is the
     * account's own. The per-address floor is a guard, not a published quota.
     */
    public function primaryQuota(): QuotaCheck
    {
        return $this->quotaChecks()[0];
    }

    /** Longest window in play, for sizing garbage collection. */
    public function longestWindow(): int
    {
        return max(array_map(static fn (QuotaCheck $c): int => $c->windowSeconds, $this->quotaChecks()));
    }
}
