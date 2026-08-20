<?php

declare(strict_types=1);

namespace Data2Md\Storage;

use Data2Md\Log\Logger;

/**
 * run() removes orphaned markdown, orphaned mappings, expired rate-limit
 * counters, rotated logs past retention, and stale temp files. It never
 * removes a markdown file that still has a mapping record, regardless of
 * age.
 *
 * delete() and prune() remove conversions by hash or by age. They are only
 * called from the CLI, never from request handling.
 */
final class Gc
{
    public function __construct(
        private readonly FileStorage $files,
        private readonly MappingStorage $mappings,
        private readonly Logger $logger,
        private readonly string $logDir,
        private readonly string $rateLimitDir,
        private readonly int $logRetentionDays,
        private readonly int $auditLogRetentionDays,
        private readonly int $rateLimitWindowSeconds,
        private readonly int $convertTimeoutSeconds,
        private readonly string $lockDir,
    ) {
    }

    public function maybeRun(float $probability): ?GcReport
    {
        if ($probability <= 0.0) {
            return null;
        }

        if ($probability < 1.0 && (random_int(1, 1_000_000) / 1_000_000) > $probability) {
            return null;
        }

        return $this->run();
    }

    public function run(): GcReport
    {
        $lock = fopen($this->lockDir . '/gc.lock', 'c');

        if ($lock === false) {
            return new GcReport();
        }

        // Non-blocking: a concurrent collection is already doing this work.
        if (!flock($lock, LOCK_EX | LOCK_NB)) {
            fclose($lock);
            return new GcReport();
        }

        try {
            $now = time();

            $report = new GcReport(
                orphanMarkdown:  $this->collectOrphanMarkdown(),
                orphanMappings:  $this->collectOrphanMappings(),
                rateLimitFiles:  $this->collectRateLimits($now),
                rotatedLogs:     $this->collectRotatedLogs($now),
                staleTempFiles:  $this->collectStaleTempFiles($now),
            );

            if ($report->total() > 0) {
                $this->logger->app('gc.run', $report->toArray());
            }

            return $report;
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    /** Removes one conversion by hash. Returns false if it did not exist. */
    public function delete(string $hash): bool
    {
        if ($this->mappings->find($hash) === null && !$this->files->markdownExists($hash)) {
            return false;
        }

        $this->files->deleteMarkdown($hash);
        $this->mappings->delete($hash);

        $this->logger->app('delete.ok', ['hash' => $hash], 'warning');

        return true;
    }

    /**
     * Removes every conversion whose mapping is older than $olderThanDays.
     *
     * @return int conversions removed
     */
    public function prune(int $olderThanDays): int
    {
        $cut     = time() - ($olderThanDays * 86400);
        $removed = 0;

        foreach ($this->mappings->hashesOlderThan($cut) as $hash) {
            $this->files->deleteMarkdown($hash);
            $this->mappings->delete($hash);
            $removed++;
        }

        if ($removed > 0) {
            $this->logger->app('prune.ok', ['removed' => $removed, 'older_than_days' => $olderThanDays], 'warning');
        }

        return $removed;
    }

    private function collectOrphanMarkdown(): int
    {
        $removed = 0;

        foreach ($this->files->orphanMarkdownHashes($this->mappings->allHashes()) as $hash) {
            if ($this->files->deleteMarkdown($hash)) {
                $removed++;
            }
        }

        return $removed;
    }

    private function collectOrphanMappings(): int
    {
        $removed = 0;

        foreach ($this->mappings->allHashes() as $hash) {
            if (!$this->files->markdownExists($hash)) {
                $this->mappings->delete($hash);
                $removed++;
            }
        }

        return $removed;
    }

    private function collectRateLimits(int $now): int
    {
        // Twice the window: anything older cannot influence a decision.
        $cut     = $now - ($this->rateLimitWindowSeconds * 2);
        $removed = 0;

        foreach (glob($this->rateLimitDir . '/*') ?: [] as $file) {
            if (is_file($file) && filemtime($file) < $cut && unlink($file)) {
                $removed++;
            }
        }

        return $removed;
    }

    private function collectRotatedLogs(int $now): int
    {
        $removed = 0;

        $retention = [
            'app.jsonl.1'   => $this->logRetentionDays,
            'audit.jsonl.1' => $this->auditLogRetentionDays,
        ];

        foreach ($retention as $name => $days) {
            $path = $this->logDir . '/' . $name;

            if (is_file($path) && filemtime($path) < ($now - ($days * 86400)) && unlink($path)) {
                $removed++;
            }
        }

        return $removed;
    }

    private function collectStaleTempFiles(int $now): int
    {
        // Twice the conversion ceiling: anything older belongs to a dead worker.
        $cut     = $now - ($this->convertTimeoutSeconds * 2);
        $removed = 0;

        foreach ($this->files->staleTempFiles($cut) as $file) {
            if (unlink($file)) {
                $removed++;
            }
        }

        return $removed;
    }
}
