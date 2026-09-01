<?php

declare(strict_types=1);

namespace Data2Md\Auth;

/** One bucket a caller has to satisfy: which counter, how many, over how long. */
final class QuotaCheck
{
    public function __construct(
        public readonly string $key,
        public readonly int $maxRequests,
        public readonly int $windowSeconds,
    ) {
    }
}
