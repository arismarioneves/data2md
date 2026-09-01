<?php

declare(strict_types=1);

namespace Data2Md\Tests\Integration;

use Data2Md\Storage\Database;
use PHPUnit\Framework\TestCase;

/**
 * Converting a database to WAL takes an exclusive lock that busy_timeout does
 * not cover. Several containers cold-starting against one volume open the same
 * new file at once, and this runs before `exec apache2-foreground`.
 */
final class DatabaseConcurrencyTest extends TestCase
{
    private string $dbPath;

    protected function setUp(): void
    {
        $this->dbPath = sys_get_temp_dir() . '/d2md-conc-' . bin2hex(random_bytes(6)) . '.sqlite';
    }

    protected function tearDown(): void
    {
        foreach ([$this->dbPath, $this->dbPath . '-wal', $this->dbPath . '-shm'] as $file) {
            if (file_exists($file)) {
                unlink($file);
            }
        }
    }

    public function testConcurrentFirstOpenAllReachWalMode(): void
    {
        $autoload = dirname(__DIR__, 2) . '/vendor/autoload.php';
        $script   = sprintf(
            'require %s; $d = new Data2Md\Storage\Database(%s);'
            . ' echo $d->pdo()->query("PRAGMA journal_mode")->fetchColumn();',
            var_export($autoload, true),
            var_export($this->dbPath, true)
        );

        $procs = $pipes = [];

        for ($i = 0; $i < 6; $i++) {
            $procs[$i] = proc_open(
                [PHP_BINARY, '-d', 'display_errors=stderr', '-r', $script],
                [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
                $pipes[$i]
            );
            self::assertIsResource($procs[$i]);
        }

        foreach ($procs as $i => $proc) {
            $stdout = trim((string) stream_get_contents($pipes[$i][1]));
            $stderr = trim((string) stream_get_contents($pipes[$i][2]));
            fclose($pipes[$i][1]);
            fclose($pipes[$i][2]);

            self::assertSame(0, proc_close($proc), "worker $i exited non-zero. stderr: $stderr");
            self::assertSame('wal', strtolower($stdout), "worker $i did not reach WAL");
        }
    }
}
