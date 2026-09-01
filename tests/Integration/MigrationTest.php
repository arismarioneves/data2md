<?php

declare(strict_types=1);

namespace Data2Md\Tests\Integration;

use Data2Md\Storage\Database;
use PHPUnit\Framework\TestCase;

/**
 * bin/data2md migrate runs from docker/entrypoint.sh under `set -eu`, before
 * `exec apache2-foreground`. An uncaught exception here is a restart loop, not
 * a failed migration.
 */
final class MigrationTest extends TestCase
{
    private string $dbPath;

    protected function setUp(): void
    {
        $this->dbPath = sys_get_temp_dir() . '/d2md-mig-' . bin2hex(random_bytes(6)) . '.sqlite';
    }

    protected function tearDown(): void
    {
        foreach ([$this->dbPath, $this->dbPath . '-wal', $this->dbPath . '-shm'] as $file) {
            if (file_exists($file)) {
                unlink($file);
            }
        }
    }

    /** A duplicate key in the migration map would silently drop one migration. */
    public function testMigrationVersionsAreContiguousFromOne(): void
    {
        $versions = Database::migrationVersions();

        self::assertNotSame([], $versions);
        self::assertSame(range(1, count($versions)), $versions);
        self::assertSame(max($versions), Database::latestVersion());
    }

    public function testFreshDatabaseMigratesToLatest(): void
    {
        $db = new Database($this->dbPath);

        self::assertSame(0, $db->version());

        $applied = $db->migrate();

        self::assertSame(Database::migrationVersions(), $applied);
        self::assertSame(Database::latestVersion(), $db->version());
    }

    public function testMigrateIsIdempotent(): void
    {
        $db = new Database($this->dbPath);
        $db->migrate();

        self::assertSame([], $db->migrate());
        self::assertSame(Database::latestVersion(), $db->version());
    }

    /** Databases in production have the mappings table at user_version 0. */
    public function testDatabasePredatingTheRunnerConverges(): void
    {
        $pdo = new \PDO('sqlite:' . $this->dbPath);
        $pdo->exec(
            'CREATE TABLE mappings (
                hash TEXT PRIMARY KEY, original_name TEXT NOT NULL, format TEXT NOT NULL,
                size_bytes INTEGER NOT NULL, created_at INTEGER NOT NULL)'
        );
        $pdo->exec('CREATE INDEX idx_mappings_created_at ON mappings (created_at)');
        $pdo->exec("INSERT INTO mappings VALUES ('abc123def456789', 'a.pdf', 'pdf', 10, 1)");
        self::assertSame(0, (int) $pdo->query('PRAGMA user_version')->fetchColumn());
        $pdo = null;

        $db = new Database($this->dbPath);
        $db->migrate();

        self::assertSame(Database::latestVersion(), $db->version());
        self::assertSame(
            '1',
            (string) $db->pdo()->query('SELECT COUNT(*) FROM mappings')->fetchColumn(),
            'existing rows survive'
        );
    }

    /** Rollback is documented as pulling the previous image tag. */
    public function testSchemaAheadOfTheBuildIsANoOpNotAnError(): void
    {
        $db = new Database($this->dbPath);
        $db->migrate();

        $future = Database::latestVersion() + 5;
        $db->pdo()->exec('PRAGMA user_version = ' . $future);

        self::assertSame([], $db->migrate());
        self::assertSame($future, $db->version());
    }

    /**
     * PRAGMA user_version lives in the database header rather than a table, so
     * whether it rolls back with the surrounding transaction is a property of
     * the SQLite build. If it did not, a half-applied migration would be
     * recorded as complete.
     */
    public function testUserVersionRollsBackWithAFailedStatement(): void
    {
        $pdo = new \PDO('sqlite:' . $this->dbPath, null, null, [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
        ]);

        $pdo->exec('BEGIN IMMEDIATE');
        $pdo->exec('CREATE TABLE ok_table (a INTEGER)');
        $pdo->exec('PRAGMA user_version = 42');

        try {
            $pdo->exec('THIS IS NOT SQL');
            self::fail('expected the bad statement to throw');
        } catch (\PDOException) {
            $pdo->exec('ROLLBACK');
        }

        self::assertSame(0, (int) $pdo->query('PRAGMA user_version')->fetchColumn());
        self::assertSame(
            [],
            $pdo->query("SELECT name FROM sqlite_master WHERE name='ok_table'")->fetchAll(\PDO::FETCH_COLUMN)
        );
    }

    /**
     * A rolling deploy runs two containers against one volume. Sequential calls
     * cannot reproduce the race, so this forks real processes.
     */
    public function testConcurrentMigrationsAllSucceed(): void
    {
        $autoload = dirname(__DIR__, 2) . '/vendor/autoload.php';
        $script   = sprintf(
            'require %s; $d = new Data2Md\Storage\Database(%s); $d->migrate(); echo $d->version();',
            var_export($autoload, true),
            var_export($this->dbPath, true)
        );

        $procs = [];
        for ($i = 0; $i < 4; $i++) {
            $procs[$i] = proc_open(
                [PHP_BINARY, '-r', $script],
                [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
                $pipes[$i]
            );
            self::assertIsResource($procs[$i]);
        }

        $expected = (string) Database::latestVersion();

        foreach ($procs as $i => $proc) {
            $stdout = (string) stream_get_contents($pipes[$i][1]);
            $stderr = (string) stream_get_contents($pipes[$i][2]);
            fclose($pipes[$i][1]);
            fclose($pipes[$i][2]);

            self::assertSame(0, proc_close($proc), "worker $i exited non-zero. stderr: $stderr");
            self::assertSame($expected, $stdout, "worker $i reported the wrong version");
        }
    }
}
