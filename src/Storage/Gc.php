<?php

declare(strict_types=1);

namespace Data2Md\Storage;

use Data2Md\Auth\LoginTokenStore;
use Data2Md\Auth\SessionStore;
use Data2Md\Log\Logger;

/**
 * run() removes orphaned markdown, orphaned mappings, expired rate-limit
 * counters, rotated logs past retention, stale temp files, and expired
 * sessions and sign-in links. It never
 * removes a markdown file that still has a mapping record, regardless of
 * age, and it never removes a markdown file recent enough to still belong
 * to a conversion in progress. When a concurrent collection already holds
 * the lock, run() returns immediately with a report flagged as skipped.
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
        /**
         * The longest window of any bucket in the directory, not any one of
         * them. Bucket filenames are hashes, so collection cannot tell a
         * 60-second bucket from a one-hour one; a shorter value here resets a
         * limiter that is still counting.
         */
        private readonly int $rateLimitMaxWindowSeconds,
        private readonly int $convertTimeoutSeconds,
        private readonly string $lockDir,
        private readonly ?SessionStore $sessions = null,
        private readonly ?LoginTokenStore $loginTokens = null,
    ) {
    }

    /**
     * Queues a sweep for after the response is on the wire.
     *
     * run() globs the whole markdown directory and scans the mappings table, so
     * its cost grows with every conversion ever made rather than with this
     * request. A shutdown callback still runs after ApiResponse's exit(), and by
     * then the response has been flushed with an explicit Content-Length, so the
     * client does not wait on it. A failure here is logged and swallowed: there
     * is no response left to fail.
     *
     * Called by a front controller, once. The pipeline must not know about it.
     */
    public static function registerShutdownSweep(
        \Data2Md\Config\Config $config,
        Logger $logger,
        Database $database,
        FileStorage $files,
        MappingStorage $mappings,
    ): void {
        $probability = $config->float('GC_PROBABILITY');

        if ($probability <= 0.0) {
            return;
        }

        $dataDir = $config->string('APP_DATA_DIR');

        register_shutdown_function(static function () use (
            $config,
            $logger,
            $database,
            $files,
            $mappings,
            $dataDir,
            $probability
        ): void {
            try {
                (new self(
                    files:                     $files,
                    mappings:                  $mappings,
                    logger:                    $logger,
                    logDir:                    $dataDir . '/logs',
                    rateLimitDir:              $dataDir . '/ratelimit',
                    logRetentionDays:          $config->int('LOG_RETENTION_DAYS'),
                    auditLogRetentionDays:     $config->int('AUDIT_LOG_RETENTION_DAYS'),
                    rateLimitMaxWindowSeconds: $config->int('RATE_LIMIT_WINDOW_SECONDS'),
                    convertTimeoutSeconds:     $config->int('CONVERT_TIMEOUT_SECONDS'),
                    lockDir:                   $dataDir . '/tmp',
                    sessions:                  new SessionStore(
                        $database,
                        $config->int('SESSION_LIFETIME_DAYS'),
                        $config->int('SESSION_IDLE_DAYS'),
                    ),
                    loginTokens:               new LoginTokenStore($database, $config->int('LOGIN_TOKEN_TTL_SECONDS')),
                ))->maybeRun($probability);
            } catch (\Throwable $e) {
                $logger->app('gc.fail', ['reason' => $e::class, 'detail' => $e->getMessage()], 'error');
            }
        });
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
            return new GcReport(skipped: true);
        }

        // Non-blocking: a concurrent collection is already doing this work.
        if (!flock($lock, LOCK_EX | LOCK_NB)) {
            fclose($lock);
            return new GcReport(skipped: true);
        }

        try {
            $now = time();

            $report = new GcReport(
                orphanMarkdown:     $this->collectOrphanMarkdown($now),
                orphanMappings:     $this->collectOrphanMappings(),
                rateLimitFiles:     $this->collectRateLimits($now),
                rotatedLogs:        $this->collectRotatedLogs($now),
                staleTempFiles:     $this->collectStaleTempFiles($now),
                expiredSessions:    $this->sessions?->purgeExpired($now) ?? 0,
                expiredLoginTokens: $this->loginTokens?->purgeExpired($now) ?? 0,
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

    private function collectOrphanMarkdown(int $now): int
    {
        // Twice the conversion ceiling: a fresher file may still belong to a
        // conversion that has written its markdown but not yet saved its
        // mapping record.
        $cut     = $now - ($this->convertTimeoutSeconds * 2);
        $removed = 0;

        foreach ($this->files->orphanMarkdownHashes($this->mappings->allHashes()) as $hash) {
            $path = $this->files->markdownPath($hash);

            if (is_file($path) && filemtime($path) < $cut && $this->files->deleteMarkdown($hash)) {
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
        $cut     = $now - ($this->rateLimitMaxWindowSeconds * 2);
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
            // Development-only channel holding live sign-in links, so it keeps
            // the shorter of the two retentions rather than its own key.
            'mail.jsonl.1'  => $this->auditLogRetentionDays,
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
