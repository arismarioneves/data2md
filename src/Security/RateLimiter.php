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
        // Hashed so raw addresses never land on disk, in the filename or in it.
        $path = $this->storageDir . '/' . hash('sha256', $identifier) . '.json';

        $handle = fopen($path, 'c+');
        if ($handle === false) {
            throw new \RuntimeException("Cannot open rate limit file: $path");
        }

        try {
            if (!flock($handle, LOCK_EX)) {
                throw new \RuntimeException("Cannot lock rate limit file: $path");
            }

            $now  = time();
            $hits = $this->readHits($handle);
            $hits = array_values(array_filter($hits, fn (int $t): bool => ($now - $t) < $this->windowSeconds));

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

    public function retryAfter(): int
    {
        return $this->windowSeconds;
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
