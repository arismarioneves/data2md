<?php

declare(strict_types=1);

namespace Data2Md\Auth;

use Data2Md\Billing\Entitlement;
use Data2Md\Config\Config;

/** Turns configuration into the tier a caller gets. */
final class TierResolver
{
    /** @param array<string,Tier> $tiers */
    private function __construct(
        private readonly array $tiers,
        private readonly int $freeIpWindowSeconds,
    ) {
    }

    /** @param array<string,Tier> $tiers */
    public static function of(array $tiers, int $freeIpWindowSeconds = 0): self
    {
        return new self($tiers, $freeIpWindowSeconds);
    }

    public static function fromConfig(Config $config): self
    {
        return new self(
            [
                Entitlement::ANON => new Tier(
                    Entitlement::ANON,
                    $config->int('TIER_ANON_MAX_CONVERSIONS'),
                    $config->int('TIER_ANON_WINDOW_SECONDS'),
                    $config->int('TIER_ANON_MAX_FILE_SIZE_MB'),
                ),
                Entitlement::FREE => new Tier(
                    Entitlement::FREE,
                    $config->int('TIER_FREE_MAX_CONVERSIONS'),
                    $config->int('TIER_FREE_WINDOW_SECONDS'),
                    $config->int('TIER_FREE_MAX_FILE_SIZE_MB'),
                ),
                Entitlement::PREMIUM => new Tier(
                    Entitlement::PREMIUM,
                    $config->int('TIER_PREMIUM_MAX_CONVERSIONS'),
                    $config->int('TIER_PREMIUM_WINDOW_SECONDS'),
                    $config->int('TIER_PREMIUM_MAX_FILE_SIZE_MB'),
                ),
            ],
            $config->int('TIER_FREE_IP_WINDOW_SECONDS'),
        );
    }

    public function for(string $plan): Tier
    {
        return $this->tiers[$plan] ?? $this->tiers[Entitlement::ANON];
    }

    /** @param array<string,mixed>|null $user */
    public function forUser(?array $user, ?int $now = null): Tier
    {
        return $this->for(Entitlement::forUser($user, $now));
    }

    /**
     * Window of the per-address floor applied to a signed-in free caller on top
     * of its per-account limit. Accounts are free and self-serve, so without it
     * one machine gets as much as it wants by making more of them. 0 disables.
     */
    public function freeIpWindowSeconds(): int
    {
        return $this->freeIpWindowSeconds;
    }
}
