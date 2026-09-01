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

    /**
     * Schema migrations in application order. PRAGMA user_version records how
     * far a given file has got.
     *
     * ADDITIVE ONLY: during a rolling deploy the old container serves requests
     * against a schema the new container has already migrated. A release may
     * add tables, nullable columns and indexes, and nothing else -- no renames,
     * no drops, no NOT NULL without a default, no type changes.
     *
     * Migration 1 uses IF NOT EXISTS because databases in production sit at
     * user_version 0 with the mappings table already present. Later migrations
     * should not.
     *
     * @var array<int,list<string>>
     */
    private const MIGRATIONS = [
        1 => [
            'CREATE TABLE IF NOT EXISTS mappings (
                hash          TEXT    PRIMARY KEY,
                original_name TEXT    NOT NULL,
                format        TEXT    NOT NULL,
                size_bytes    INTEGER NOT NULL,
                created_at    INTEGER NOT NULL
            )',
            'CREATE INDEX IF NOT EXISTS idx_mappings_created_at ON mappings (created_at)',
        ],
        2 => [
            // COLLATE NOCASE is ASCII-only, so email is also normalised in PHP
            // before it reaches here; the collation is what stops two rows
            // differing only in case from both existing.
            'CREATE TABLE users (
                id                      INTEGER PRIMARY KEY AUTOINCREMENT,
                email                   TEXT    NOT NULL UNIQUE COLLATE NOCASE,
                created_at              INTEGER NOT NULL,
                last_login_at           INTEGER,
                stripe_customer_id      TEXT,
                subscription_id         TEXT,
                subscription_status     TEXT,
                subscription_price_id   TEXT,
                current_period_end      INTEGER,
                cancel_at_period_end    INTEGER NOT NULL DEFAULT 0,
                subscription_updated_at INTEGER,
                premium_until           INTEGER
            )',
            'CREATE INDEX idx_users_stripe_customer ON users (stripe_customer_id)',

            'CREATE TABLE sessions (
                token_hash   TEXT    PRIMARY KEY,
                user_id      INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
                csrf_token   TEXT    NOT NULL,
                created_at   INTEGER NOT NULL,
                last_seen_at INTEGER NOT NULL,
                expires_at   INTEGER NOT NULL
            )',
            'CREATE INDEX idx_sessions_user ON sessions (user_id)',
            'CREATE INDEX idx_sessions_expires ON sessions (expires_at)',

            'CREATE TABLE login_tokens (
                token_hash TEXT    PRIMARY KEY,
                email      TEXT    NOT NULL COLLATE NOCASE,
                created_at INTEGER NOT NULL,
                expires_at INTEGER NOT NULL,
                used_at    INTEGER
            )',
            'CREATE INDEX idx_login_tokens_email ON login_tokens (email)',
            'CREATE INDEX idx_login_tokens_expires ON login_tokens (expires_at)',
        ],
        3 => [
            // SQLite allows REFERENCES in ADD COLUMN only while the default is
            // NULL, and a foreign key cannot be added to a table afterwards at
            // all — so this clause has to be here or never. Deleting an account
            // detaches its conversions rather than removing them: the markdown
            // stays reachable by hash, as it is for an anonymous one.
            'ALTER TABLE mappings ADD COLUMN user_id INTEGER REFERENCES users(id) ON DELETE SET NULL',
            'CREATE INDEX idx_mappings_user ON mappings (user_id, created_at)',
        ],
        4 => [
            // One row per account: UNIQUE on user_id is what makes rotation a
            // replacement, so an old credential cannot outlive its replacement
            // because someone forgot a second step. SQLite indexes both UNIQUE
            // columns, and the per-request lookup is the one on token_hash.
            'CREATE TABLE api_tokens (
                id           INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id      INTEGER NOT NULL UNIQUE REFERENCES users(id) ON DELETE CASCADE,
                token_hash   TEXT    NOT NULL UNIQUE,
                prefix       TEXT    NOT NULL,
                last4        TEXT    NOT NULL,
                created_at   INTEGER NOT NULL,
                rotated_at   INTEGER,
                last_used_at INTEGER
            )',
        ],
        5 => [
            // One row per Stripe event, claimed before it is handled, so a
            // redelivery or two workers racing cannot apply it twice. Kept for a
            // month: long past Stripe's own retry window, and reprocessing an
            // event older than that is harmless anyway, because the handler
            // re-reads current state from the API instead of trusting a payload.
            'CREATE TABLE webhook_events (
                event_id     TEXT    PRIMARY KEY,
                type         TEXT,
                received_at  INTEGER NOT NULL,
                processed_at INTEGER
            )',
            'CREATE INDEX idx_webhook_events_received ON webhook_events (received_at)',
        ],
    ];

    /** @return list<int> */
    public static function migrationVersions(): array
    {
        return array_map('intval', array_keys(self::MIGRATIONS));
    }

    public static function latestVersion(): int
    {
        return max(self::migrationVersions());
    }

    /** Schema version recorded in this file. 0 for a new or pre-runner database. */
    public function version(): int
    {
        return $this->readVersion($this->pdo());
    }

    /**
     * Applies every migration this file has not seen yet.
     *
     * @return list<int> versions applied by this call; empty when already current
     */
    public function migrate(): array
    {
        $pdo = $this->pdo();

        // A migration may wait longer than a request: two containers sharing the
        // volume during a rolling deploy contend on the write lock below.
        $pdo->exec('PRAGMA busy_timeout = 30000');

        try {
            $current = $this->readVersion($pdo);

            // Ahead of this build means an older image against a volume a newer
            // one migrated, which docs/DEPLOY.md documents as the rollback path.
            // The caller logs it.
            if ($current > self::latestVersion()) {
                return [];
            }

            $applied = [];

            foreach (self::MIGRATIONS as $version => $statements) {
                if ($version <= $current) {
                    continue;
                }

                if ($this->apply($pdo, (int) $version, $statements)) {
                    $applied[] = (int) $version;
                }
            }

            return $applied;
        } finally {
            $pdo->exec('PRAGMA busy_timeout = 5000');
        }
    }

    /**
     * @param list<string> $statements
     * @return bool false when another process had already applied this version
     */
    private function apply(\PDO $pdo, int $version, array $statements): bool
    {
        // IMMEDIATE takes the write lock up front, so a second container waits
        // here rather than racing into the DDL. It does not block WAL readers.
        $pdo->exec('BEGIN IMMEDIATE');

        try {
            // Re-read under the lock: another process may have applied this
            // version in the meantime, and re-running the DDL would throw.
            if ($this->readVersion($pdo) >= $version) {
                $pdo->exec('ROLLBACK');
                return false;
            }

            foreach ($statements as $sql) {
                $pdo->exec($sql);
            }

            // PRAGMA cannot be parameterised.
            $pdo->exec('PRAGMA user_version = ' . (int) $version);

            $pdo->exec('COMMIT');

            return true;
        } catch (\Throwable $e) {
            try {
                $pdo->exec('ROLLBACK');
            } catch (\Throwable) {
                // A transaction already gone must not replace the real error.
            }

            throw $e;
        }
    }

    private function readVersion(\PDO $pdo): int
    {
        return (int) $pdo->query('PRAGMA user_version')->fetchColumn();
    }
}
