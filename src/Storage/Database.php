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

        // busy_timeout must be set before any statement that can find the
        // database locked -- including the journal_mode pragma itself. On a
        // genuinely concurrent cold start (a rolling deploy where the old and
        // new containers share the volume), WAL is exactly the pragma that
        // can hit a lock, so it must not run before the timeout is armed.
        $pdo->exec('PRAGMA busy_timeout = 5000');
        // WAL lets /download read while /api writes; without it the reader
        // blocks on every conversion.
        $pdo->exec('PRAGMA journal_mode = WAL');
        $pdo->exec('PRAGMA synchronous = NORMAL');
        $pdo->exec('PRAGMA foreign_keys = ON');

        return $this->pdo = $pdo;
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
