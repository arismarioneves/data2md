<?php

declare(strict_types=1);

namespace Data2Md\Security;

class RateLimiter
{
    public function __construct(
        private readonly string $storageDir,
        private readonly int $maxRequests = 10,
        private readonly int $windowSeconds = 60
    ) {
        if (!is_dir($storageDir) && !mkdir($storageDir, 0750, true)) {
            throw new \RuntimeException("Cannot create rate limiter storage: $storageDir");
        }
    }

    public function isAllowed(string $identifier): bool
    {
        // Use a hash so raw IPs are not stored on disk
        $key  = hash('sha256', $identifier);
        $file = $this->storageDir . '/' . $key . '.json';
        $now  = time();

        $lock = fopen($file . '.lock', 'c');
        flock($lock, LOCK_EX);
        try {
            $data = $this->load($file);
            $data['hits'] = array_values(
                array_filter($data['hits'] ?? [], fn(int $t) => ($now - $t) < $this->windowSeconds)
            );

            if (count($data['hits']) >= $this->maxRequests) {
                return false;
            }

            $data['hits'][] = $now;
            file_put_contents($file, json_encode($data), LOCK_EX);
            return true;
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    private function load(string $file): array
    {
        if (!file_exists($file)) {
            return ['hits' => []];
        }
        $content = file_get_contents($file);
        return json_decode($content ?: '{}', true) ?? ['hits' => []];
    }
}
