<?php

declare(strict_types=1);

namespace Data2Md\Auth;

use Data2Md\Billing\Entitlement;

/** What one plan is allowed to do. */
final class Tier
{
    public function __construct(
        public readonly string $plan,
        public readonly int $maxConversions,
        public readonly int $windowSeconds,
        public readonly int $maxFileSizeMb,
    ) {
    }

    public function maxFileSizeBytes(): int
    {
        return $this->maxFileSizeMb * 1024 * 1024;
    }

    public function maxFileSizeLabel(): string
    {
        return \Data2Md\format_bytes_label($this->maxFileSizeMb);
    }

    /** The HTTP API is what a subscription buys. */
    public function allowsApi(): bool
    {
        return $this->plan === Entitlement::PREMIUM;
    }
}
