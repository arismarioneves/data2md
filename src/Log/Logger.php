<?php

declare(strict_types=1);

namespace Data2Md\Log;

/**
 * Three log channels:
 *
 *   app.jsonl   — operational, no PII. Mirrored to stderr, which the official
 *                 PHP image forwards to `docker logs` and so to any host's log
 *                 viewer.
 *   audit.jsonl — who converted what, when. Mode 0640, short retention.
 *   mail.jsonl  — sign-in links, when MAIL_TRANSPORT=log. Mode 0600 and never
 *                 mirrored: the entries are live credentials.
 */
final class Logger
{
    public const CHANNEL_APP   = 'app';
    public const CHANNEL_AUDIT = 'audit';
    public const CHANNEL_MAIL  = 'mail';

    /** @var list<string> */
    public const CHANNELS = [self::CHANNEL_APP, self::CHANNEL_AUDIT, self::CHANNEL_MAIL];

    public function __construct(
        private readonly string $logDir,
        private readonly int $maxBytes = 10_485_760,
        private readonly bool $mirrorToStderr = true,
    ) {
    }

    /** @param array<string,mixed> $fields */
    public function app(string $event, array $fields = [], string $level = 'info'): void
    {
        $entry = ['ts' => self::now(), 'lvl' => $level, 'evt' => $event] + $fields;
        $line  = $this->encode($entry);

        $this->write(self::CHANNEL_APP, $line, 0640);

        if ($this->mirrorToStderr) {
            error_log('[data2md] ' . $line);
        }
    }

    /** @param array<string,mixed> $fields */
    public function audit(array $fields): void
    {
        $entry = ['ts' => self::now()] + $fields;
        $this->write(self::CHANNEL_AUDIT, $this->encode($entry), 0640);
    }

    /**
     * Stands in for sending a message. The entries carry a working sign-in
     * link, so this is 0600 and never reaches stderr.
     *
     * @param array<string,mixed> $fields
     */
    public function mail(array $fields): void
    {
        $entry = ['ts' => self::now()] + $fields;
        $this->write(self::CHANNEL_MAIL, $this->encode($entry), 0600);
    }

    /**
     * Coarsen an address enough to spot abuse without keeping an identifier:
     * IPv4 to /24, IPv6 to /48.
     */
    public static function truncateIp(string $ip): string
    {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
            $parts = explode('.', $ip);
            $parts[3] = '0';
            return implode('.', $parts);
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false) {
            $packed = inet_pton($ip);
            if ($packed === false) {
                return 'invalid';
            }
            $masked = substr($packed, 0, 6) . str_repeat("\0", 10);
            return (string) inet_ntop($masked);
        }

        return 'invalid';
    }

    /** @param array<string,mixed> $entry */
    private function encode(array $entry): string
    {
        return (string) json_encode(
            $entry,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
        );
    }

    private function write(string $channel, string $line, int $mode): void
    {
        $path = $this->logDir . '/' . $channel . '.jsonl';

        $this->rotateIfNeeded($path);

        $existed = file_exists($path);
        file_put_contents($path, $line . "\n", FILE_APPEND | LOCK_EX);

        if (!$existed && !chmod($path, $mode)) {
            $reason = error_get_last()['message'] ?? 'unknown reason';
            error_log(sprintf(
                '[data2md] chmod(%s, 0%o) failed (%s); this file may be more permissive than intended',
                $path,
                $mode,
                $reason
            ));
        }
    }

    /**
     * Size-based rotation, kept in the Logger because the container has neither
     * cron nor logrotate. Only one generation is retained; the GC removes the
     * rotated file once it is past retention.
     *
     * Double-checked under a lock on the live file: the unlocked check above
     * is a fast path so an ordinary write doesn't pay for a lock it will
     * almost never need. Once that check says "oversized", we open the live
     * file, take an exclusive lock, and re-check size and existence while
     * holding it. Without the re-check, two workers can both observe
     * "oversized" before either renames, and the second rename would clobber
     * the generation the first just created. The re-check catches a worker
     * that lost the race: by the time it gets the lock, the file it opened
     * has either already been renamed away (file_exists fails) or replaced
     * by a fresh, small one (filesize fails the threshold), so it backs off
     * instead of rotating a second time.
     */
    private function rotateIfNeeded(string $path): void
    {
        if (!file_exists($path)) {
            return;
        }

        clearstatcache(true, $path);
        if (filesize($path) < $this->maxBytes) {
            return;
        }

        // 'r+' never creates: if the file is gone by the time we get here,
        // another worker already rotated it, and that is not our problem.
        $lock = @fopen($path, 'r+');
        if ($lock === false) {
            return;
        }

        try {
            if (!flock($lock, LOCK_EX)) {
                return;
            }

            clearstatcache(true, $path);
            if (!file_exists($path) || filesize($path) < $this->maxBytes) {
                return;
            }

            rename($path, $path . '.1');
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    private static function now(): string
    {
        return gmdate('Y-m-d\TH:i:s\Z');
    }
}
