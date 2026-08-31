<?php

declare(strict_types=1);

namespace Data2Md\Tests\Integration;

use Data2Md\Auth\LoginTokenStore;
use Data2Md\Auth\SessionStore;
use Data2Md\Auth\UserStore;
use Data2Md\Log\Logger;
use Data2Md\Storage\Database;
use Data2Md\Storage\FileStorage;
use Data2Md\Storage\Gc;
use Data2Md\Storage\MappingStorage;
use PHPUnit\Framework\TestCase;

final class GcTest extends TestCase
{
    private string $root;
    private Database $db;
    private FileStorage $files;
    private MappingStorage $mappings;
    private SessionStore $sessions;
    private LoginTokenStore $loginTokens;
    private Gc $gc;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/d2md-gc-' . bin2hex(random_bytes(6));

        foreach (['md', 'tmp', 'logs', 'ratelimit', 'db'] as $sub) {
            mkdir($this->root . '/' . $sub, 0770, true);
        }

        $this->db = new Database($this->root . '/db/mappings.sqlite');
        $this->db->migrate();

        $this->files       = new FileStorage($this->root . '/md', $this->root . '/tmp');
        $this->mappings    = new MappingStorage($this->db);
        $this->sessions    = new SessionStore($this->db, 30, 14);
        $this->loginTokens = new LoginTokenStore($this->db, 900);

        $this->gc = new Gc(
            files:                     $this->files,
            mappings:                  $this->mappings,
            logger:                    new Logger($this->root . '/logs', 10_485_760, false),
            logDir:                    $this->root . '/logs',
            rateLimitDir:              $this->root . '/ratelimit',
            logRetentionDays:          90,
            auditLogRetentionDays:     30,
            rateLimitMaxWindowSeconds: 60,
            convertTimeoutSeconds:     900,
            lockDir:                   $this->root . '/tmp',
            sessions:                  $this->sessions,
            loginTokens:               $this->loginTokens,
        );
    }

    protected function tearDown(): void
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->root, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }

        rmdir($this->root);
    }

    private function seedConversion(string $hash, int $ageDays): void
    {
        file_put_contents($this->files->markdownPath($hash), "# doc\n");
        $this->mappings->save($hash, 'doc.pdf', 'pdf', 100, time() - ($ageDays * 86400));
    }

    public function testAgedConversionsAreNeverRemovedAutomatically(): void
    {
        // The markdown is the product, not a cache. Ten years old or ten
        // seconds old, only an explicit command removes it.
        $this->seedConversion('aaaaaaaaaaaaaaa', 3650);

        $report = $this->gc->run();

        self::assertSame(0, $report->total());
        self::assertTrue($this->files->markdownExists('aaaaaaaaaaaaaaa'));
        self::assertNotNull($this->mappings->find('aaaaaaaaaaaaaaa'));
    }

    public function testMarkdownWithoutARecordIsRemoved(): void
    {
        $path = $this->files->markdownPath('ccccccccccccccc');
        file_put_contents($path, '# orphan');
        touch($path, time() - 3600);

        $report = $this->gc->run();

        self::assertSame(1, $report->orphanMarkdown);
        self::assertFalse($this->files->markdownExists('ccccccccccccccc'));
    }

    public function testRecentMarkdownWithoutARecordIsNotRemoved(): void
    {
        // A conversion writes its markdown before saving the mapping record,
        // so a fresh file with no record yet is a conversion in progress,
        // not an orphan.
        file_put_contents($this->files->markdownPath('bbbbbbbbbbbbbbb'), '# in progress');

        $report = $this->gc->run();

        self::assertSame(0, $report->orphanMarkdown);
        self::assertTrue($this->files->markdownExists('bbbbbbbbbbbbbbb'));
    }

    public function testRecordWithoutMarkdownIsRemoved(): void
    {
        $this->mappings->save('ddddddddddddddd', 'gone.pdf', 'pdf', 100, time());

        $report = $this->gc->run();

        self::assertSame(1, $report->orphanMappings);
        self::assertNull($this->mappings->find('ddddddddddddddd'));
    }

    public function testStaleRateLimitFilesAreRemoved(): void
    {
        $fresh = $this->root . '/ratelimit/fresh.json';
        $stale = $this->root . '/ratelimit/stale.json';

        file_put_contents($fresh, '{}');
        file_put_contents($stale, '{}');
        touch($stale, time() - 3600);

        $report = $this->gc->run();

        self::assertSame(1, $report->rateLimitFiles);
        self::assertFileExists($fresh);
        self::assertFileDoesNotExist($stale);
    }

    public function testExpiredSessionsAndSignInLinksAreCollected(): void
    {
        $userId = (new UserStore($this->db))->ensure('alice@example.com');
        $past   = time() - (60 * 86400);

        $live    = $this->sessions->create($userId);
        $expired = $this->sessions->create($userId, $past);

        $this->loginTokens->issue('alice@example.com');
        $this->loginTokens->issue('alice@example.com', $past);

        $report = $this->gc->run();

        self::assertSame(1, $report->expiredSessions);
        self::assertSame(1, $report->expiredLoginTokens);
        self::assertNotNull($this->sessions->find($live), 'a live session survives');
        self::assertNull($this->sessions->find($expired));
        self::assertSame(1, $this->loginTokens->count());
    }

    /** A bucket collected before its window elapses stops enforcing its limit. */
    public function testRateLimitCutScalesWithTheConfiguredWindow(): void
    {
        $gc = $this->makeGcWithMaxWindow(3600);

        // Cut is 2 x 3600 = 7200s ago.
        $withinWindow = $this->root . '/ratelimit/within.json';
        $pastWindow   = $this->root . '/ratelimit/past.json';

        file_put_contents($withinWindow, '{}');
        file_put_contents($pastWindow, '{}');
        touch($withinWindow, time() - 5_000);
        touch($pastWindow, time() - 10_000);

        $report = $gc->run();

        self::assertSame(1, $report->rateLimitFiles);
        self::assertFileExists(
            $withinWindow,
            'a bucket still inside its window must survive collection, or the limit it enforces resets'
        );
        self::assertFileDoesNotExist($pastWindow);
    }

    private function makeGcWithMaxWindow(int $seconds): Gc
    {
        return new Gc(
            files:                     $this->files,
            mappings:                  $this->mappings,
            logger:                    new Logger($this->root . '/logs', 10_485_760, false),
            logDir:                    $this->root . '/logs',
            rateLimitDir:              $this->root . '/ratelimit',
            logRetentionDays:          90,
            auditLogRetentionDays:     30,
            rateLimitMaxWindowSeconds: $seconds,
            convertTimeoutSeconds:     900,
            lockDir:                   $this->root . '/tmp',
        );
    }

    public function testRotatedLogsPastRetentionAreRemoved(): void
    {
        $old = $this->root . '/logs/app.jsonl.1';
        file_put_contents($old, '{}');
        touch($old, time() - (100 * 86400));

        $current = $this->root . '/logs/app.jsonl';
        file_put_contents($current, '{}');

        $report = $this->gc->run();

        self::assertSame(1, $report->rotatedLogs);
        self::assertFileDoesNotExist($old);
        self::assertFileExists($current, 'the live log is never removed');
    }

    public function testAuditLogUsesItsOwnShorterRetention(): void
    {
        $audit = $this->root . '/logs/audit.jsonl.1';
        file_put_contents($audit, '{}');
        touch($audit, time() - (45 * 86400)); // past audit's 30d, inside app's 90d

        $this->gc->run();

        self::assertFileDoesNotExist($audit);
    }

    public function testStaleTempFilesAreRemoved(): void
    {
        $stale = $this->root . '/tmp/abandoned.original';
        file_put_contents($stale, 'leftover');
        touch($stale, time() - 7200);

        $fresh = $this->root . '/tmp/in-progress.original';
        file_put_contents($fresh, 'working');

        $report = $this->gc->run();

        self::assertSame(1, $report->staleTempFiles);
        self::assertFileDoesNotExist($stale);
        self::assertFileExists($fresh);
    }

    public function testSemaphoreLocksAreNeverRemoved(): void
    {
        $lock = $this->root . '/tmp/slot-0.lock';
        touch($lock, time() - 86400);

        $this->gc->run();

        self::assertFileExists($lock, 'removing a held lock file would break the semaphore');
    }

    public function testRunIsIdempotent(): void
    {
        $path = $this->files->markdownPath('eeeeeeeeeeeeeee');
        file_put_contents($path, '# orphan');
        touch($path, time() - 3600);

        self::assertSame(1, $this->gc->run()->orphanMarkdown);
        self::assertSame(0, $this->gc->run()->orphanMarkdown);
    }

    public function testRunReportsNotSkippedWhenItCompletes(): void
    {
        self::assertFalse($this->gc->run()->skipped);
    }

    public function testRunReportsSkippedWhenAnotherCollectionIsInProgress(): void
    {
        $lock = fopen($this->root . '/tmp/gc.lock', 'c');
        flock($lock, LOCK_EX);

        $report = $this->gc->run();

        self::assertTrue($report->skipped);
        self::assertSame(0, $report->total());

        flock($lock, LOCK_UN);
        fclose($lock);
    }

    public function testMaybeRunNeverRunsAtZeroProbability(): void
    {
        file_put_contents($this->files->markdownPath('fffffffffffffff'), '# orphan');

        self::assertNull($this->gc->maybeRun(0.0));
        self::assertTrue($this->files->markdownExists('fffffffffffffff'));
    }

    public function testMaybeRunAlwaysRunsAtProbabilityOne(): void
    {
        $path = $this->files->markdownPath('aaaaaaaaaaaaaab');
        file_put_contents($path, '# orphan');
        touch($path, time() - 3600);

        self::assertNotNull($this->gc->maybeRun(1.0));
        self::assertFalse($this->files->markdownExists('aaaaaaaaaaaaaab'));
    }

    public function testReportTotal(): void
    {
        $path = $this->files->markdownPath('aaaaaaaaaaaaaac');
        file_put_contents($path, '# orphan');
        touch($path, time() - 3600);
        $this->mappings->save('aaaaaaaaaaaaaad', 'gone.pdf', 'pdf', 100, time());

        self::assertSame(2, $this->gc->run()->total());
    }

    public function testDeleteRemovesBothFileAndRecord(): void
    {
        $this->seedConversion('aaaaaaaaaaaaaae', 1);

        self::assertTrue($this->gc->delete('aaaaaaaaaaaaaae'));

        self::assertFalse($this->files->markdownExists('aaaaaaaaaaaaaae'));
        self::assertNull($this->mappings->find('aaaaaaaaaaaaaae'));
    }

    public function testDeleteReturnsFalseForAnUnknownHash(): void
    {
        self::assertFalse($this->gc->delete('000000000000000'));
    }

    public function testPruneRemovesOnlyBeyondTheCut(): void
    {
        $this->seedConversion('aaaaaaaaaaaaaaf', 100);
        $this->seedConversion('aaaaaaaaaaaaaa0', 5);

        self::assertSame(1, $this->gc->prune(30));

        self::assertFalse($this->files->markdownExists('aaaaaaaaaaaaaaf'));
        self::assertTrue($this->files->markdownExists('aaaaaaaaaaaaaa0'));
    }
}
