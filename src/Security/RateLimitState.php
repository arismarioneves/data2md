<?php

declare(strict_types=1);

namespace Data2Md\Security;

/** What a caller has left in one bucket, as the X-RateLimit headers report it. */
final class RateLimitState
{
    public function __construct(
        public readonly int $limit,
        public readonly int $used,
        /** Unix timestamp, so a client does not have to guess between the two conventions. */
        public readonly int $resetAt,
    ) {
    }

    public function remaining(): int
    {
        return max(0, $this->limit - $this->used);
    }

    public function retryAfter(int $now): int
    {
        return max(1, $this->resetAt - $now);
    }
}
