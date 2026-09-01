<?php

declare(strict_types=1);

namespace Data2Md\Security;

/**
 * Builds limiters that share one directory but differ in limit and window,
 * because a caller can be subject to several buckets at once.
 */
final class RateLimiterFactory
{
    public function __construct(private readonly string $storageDir)
    {
    }

    public function make(int $maxRequests, int $windowSeconds): RateLimiter
    {
        return new RateLimiter($this->storageDir, $maxRequests, $windowSeconds);
    }
}
