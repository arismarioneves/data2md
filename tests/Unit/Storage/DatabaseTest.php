<?php

declare(strict_types=1);

namespace Data2Md\Tests\Unit\Storage;

use Data2Md\Storage\Database;
use PHPUnit\Framework\TestCase;

final class DatabaseTest extends TestCase
{
    private string $dbPath;

    protected function setUp(): void
    {
        $this->dbPath = sys_get_temp_dir() . '/d2md-db-' . bin2hex(random_bytes(6)) . '.sqlite';
    }

    protected function tearDown(): void
    {
        foreach ([$this->dbPath, $this->dbPath . '-wal', $this->dbPath . '-shm'] as $file) {
            if (file_exists($file)) {
                unlink($file);
            }
        }
    }

    public function testMigrateIsIdempotent(): void
    {
        $db = new Database($this->dbPath);
        $db->migrate();
        $db->migrate();

        $tables = $db->pdo()
            ->query("SELECT name FROM sqlite_master WHERE type='table' AND name='mappings'")
            ->fetchAll(\PDO::FETCH_COLUMN);

        self::assertSame(['mappings'], $tables);
    }

    public function testWalIsEnabled(): void
    {
        $db = new Database($this->dbPath);
        $db->migrate();

        $mode = $db->pdo()->query('PRAGMA journal_mode')->fetchColumn();

        self::assertSame('wal', strtolower((string) $mode), 'WAL lets /download read while /api writes');
    }

    public function testMissingParentDirectoryIsCreated(): void
    {
        $nested = sys_get_temp_dir() . '/d2md-nested-' . bin2hex(random_bytes(6)) . '/db/mappings.sqlite';

        (new Database($nested))->migrate();

        self::assertFileExists($nested);

        unlink($nested);
        rmdir(dirname($nested));
        rmdir(dirname($nested, 2));
    }

    public function testPdoThrowsOnError(): void
    {
        $db = new Database($this->dbPath);
        $db->migrate();

        $this->expectException(\PDOException::class);
        $db->pdo()->query('SELECT * FROM table_that_does_not_exist');
    }
}
