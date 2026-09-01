<?php

declare(strict_types=1);

namespace Data2Md\Security;

/**
 * Sliding-window limiter, one file per identifier.
 *
 * The data file is its own lock: opened 'c+' with an exclusive flock held for
 * the whole read-modify-write cycle.
 */
final class RateLimiter
{
    public function __construct(
        private readonly string $storageDir,
        private readonly int $maxRequests,
        private readonly int $windowSeconds,
    ) {
        if (!is_dir($storageDir) && !mkdir($storageDir, 0750, true) && !is_dir($storageDir)) {
            throw new \RuntimeException("Cannot create rate limiter storage: $storageDir");
        }
    }

    public function isAllowed(string $identifier): bool
    {
        $handle = $this->open($identifier);

        try {
            $this->lock($handle, LOCK_EX, $identifier);

            $now  = time();
            $hits = $this->withinWindow($this->readHits($handle), $now);

            if (count($hits) >= $this->maxRequests) {
                $this->writeHits($handle, $hits);
                return false;
            }

            $hits[] = $now;
            $this->writeHits($handle, $hits);

            return true;
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    /**
     * Releases the most recent hit, for a failure the caller has decided not to
     * charge for after already charging.
     *
     * Under concurrency this may release another request's hit rather than the
     * caller's own, which leaves the count the same either way.
     */
    public function refund(string $identifier): void
    {
        $handle = $this->open($identifier);

        try {
            $this->lock($handle, LOCK_EX, $identifier);

            $hits = $this->withinWindow($this->readHits($handle), time());
            array_pop($hits);

            $this->writeHits($handle, $hits);
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    /** Seconds until the oldest hit leaves the window, at least one. */
    public function retryAfter(string $identifier): int
    {
        $now = time();

        return $this->snapshot($identifier, $now)->retryAfter($now);
    }

    /**
     * Reads a bucket without charging it, for the X-RateLimit headers.
     *
     * Shared lock: a report must not serialise against other requests, and it
     * has nothing to write.
     */
    public function snapshot(string $identifier, ?int $now = null): RateLimitState
    {
        $now  = $now ?? time();
        $path = $this->pathFor($identifier);
        $idle = new RateLimitState($this->maxRequests, 0, $now + $this->windowSeconds);

        if (!is_file($path)) {
            return $idle;
        }

        $handle = fopen($path, 'rb');
        if ($handle === false) {
            return $idle;
        }

        try {
            $this->lock($handle, LOCK_SH, $identifier);

            $hits = $this->withinWindow($this->readHits($handle), $now);

            if ($hits === []) {
                return $idle;
            }

            return new RateLimitState($this->maxRequests, count($hits), min($hits) + $this->windowSeconds);
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    // Hashed so raw addresses never land on disk, in the filename or in it.
    private function pathFor(string $identifier): string
    {
        return $this->storageDir . '/' . hash('sha256', $identifier) . '.json';
    }

    /** @return resource */
    private function open(string $identifier)
    {
        $path   = $this->pathFor($identifier);
        $handle = fopen($path, 'c+');

        if ($handle === false) {
            throw new \RuntimeException("Cannot open rate limit file: $path");
        }

        return $handle;
    }

    /** @param resource $handle */
    private function lock($handle, int $mode, string $identifier): void
    {
        if (!flock($handle, $mode)) {
            throw new \RuntimeException('Cannot lock rate limit file: ' . $this->pathFor($identifier));
        }
    }

    /**
     * @param list<int> $hits
     * @return list<int>
     */
    private function withinWindow(array $hits, int $now): array
    {
        return array_values(array_filter($hits, fn (int $t): bool => ($now - $t) < $this->windowSeconds));
    }

    /**
     * @param resource $handle
     * @return list<int>
     */
    private function readHits($handle): array
    {
        rewind($handle);
        $raw = (string) stream_get_contents($handle);

        if (trim($raw) === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded) || !isset($decoded['hits']) || !is_array($decoded['hits'])) {
            return [];
        }

        return array_values(array_map('intval', array_filter($decoded['hits'], 'is_numeric')));
    }

    /**
     * @param resource $handle
     * @param list<int> $hits
     */
    private function writeHits($handle, array $hits): void
    {
        rewind($handle);
        ftruncate($handle, 0);
        fwrite($handle, (string) json_encode(['hits' => $hits]));
        fflush($handle);
    }
}
