<?php

declare(strict_types=1);

namespace Data2Md\Storage;

final class Database
{
    private ?\PDO $pdo = null;

    public function __construct(private readonly string $path)
    {
    }

    public function pdo(): \PDO
    {
        if ($this->pdo !== null) {
            return $this->pdo;
        }

        $dir = dirname($this->path);
        if (!is_dir($dir) && !mkdir($dir, 0750, true) && !is_dir($dir)) {
            throw new \RuntimeException("Cannot create database directory: $dir");
        }

        $pdo = new \PDO('sqlite:' . $this->path, null, null, [
            \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            \PDO::ATTR_EMULATE_PREPARES   => false,
        ]);

        // Holds account e-mails, Stripe customer ids and token hashes. SQLite
        // gives the -wal and -shm sidecars the same mode as the database.
        @chmod($this->path, 0640);

        // Armed before any statement that can find the database locked.
        $pdo->exec('PRAGMA busy_timeout = 5000');
        // WAL lets /download read while /api writes; without it the reader
        // blocks on every conversion.
        self::enableWal($pdo);
        $pdo->exec('PRAGMA synchronous = NORMAL');
        $pdo->exec('PRAGMA foreign_keys = ON');

        return $this->pdo = $pdo;
    }

    /**
     * Converting a database to WAL takes an exclusive lock that busy_timeout
     * does not cover, so concurrent first opens of the same new file can fail
     * outright with "database is locked". Setting the mode a file already has is
     * a no-op, so this only contends while the file is being created.
     */
    private static function enableWal(\PDO $pdo): void
    {
        $deadline = microtime(true) + 5.0;

        while (true) {
            try {
                $pdo->exec('PRAGMA journal_mode = WAL');
                return;
            } catch (\PDOException $e) {
                if (self::journalMode($pdo) === 'wal') {
                    return;
                }

                if (microtime(true) >= $deadline) {
                    throw $e;
                }

                usleep(random_int(5_000, 25_000));
            }
        }
    }

    private static function journalMode(\PDO $pdo): string
    {
        try {
            return strtolower((string) $pdo->query('PRAGMA journal_mode')->fetchColumn());
        } catch (\PDOException) {
            return '';
        }
    }

    public function migrate(): void
    {
        $this->pdo()->exec(
            <<<'SQL'
            CREATE TABLE IF NOT EXISTS mappings (
                hash          TEXT    PRIMARY KEY,
                original_name TEXT    NOT NULL,
                format        TEXT    NOT NULL,
                size_bytes    INTEGER NOT NULL,
                created_at    INTEGER NOT NULL
            );
            SQL
        );

        $this->pdo()->exec(
            'CREATE INDEX IF NOT EXISTS idx_mappings_created_at ON mappings (created_at)'
        );
    }
}
