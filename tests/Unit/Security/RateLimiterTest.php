<?php

declare(strict_types=1);

namespace Data2Md\Tests\Unit\Security;

use Data2Md\Security\RateLimiter;
use PHPUnit\Framework\TestCase;

final class RateLimiterTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/d2md-rl-' . bin2hex(random_bytes(6));
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir . '/*') ?: [] as $file) {
            unlink($file);
        }
        if (is_dir($this->dir)) {
            rmdir($this->dir);
        }
    }

    public function testAllowsUpToTheLimitThenBlocks(): void
    {
        $limiter = new RateLimiter($this->dir, 3, 60);

        self::assertTrue($limiter->isAllowed('1.2.3.4'));
        self::assertTrue($limiter->isAllowed('1.2.3.4'));
        self::assertTrue($limiter->isAllowed('1.2.3.4'));
        self::assertFalse($limiter->isAllowed('1.2.3.4'));
    }

    public function testIdentifiersAreIsolated(): void
    {
        $limiter = new RateLimiter($this->dir, 1, 60);

        self::assertTrue($limiter->isAllowed('1.2.3.4'));
        self::assertFalse($limiter->isAllowed('1.2.3.4'));
        self::assertTrue($limiter->isAllowed('5.6.7.8'));
    }

    public function testWindowSlides(): void
    {
        $limiter = new RateLimiter($this->dir, 2, 1);

        self::assertTrue($limiter->isAllowed('1.2.3.4'));
        self::assertTrue($limiter->isAllowed('1.2.3.4'));
        self::assertFalse($limiter->isAllowed('1.2.3.4'));

        sleep(2);

        self::assertTrue($limiter->isAllowed('1.2.3.4'), 'hits older than the window must expire');
    }

    public function testStorageDirectoryIsCreated(): void
    {
        self::assertDirectoryDoesNotExist($this->dir);
        new RateLimiter($this->dir, 1, 60);
        self::assertDirectoryExists($this->dir);
    }

    public function testRawIdentifierIsNotWrittenToDisk(): void
    {
        (new RateLimiter($this->dir, 1, 60))->isAllowed('203.0.113.77');

        foreach (glob($this->dir . '/*') ?: [] as $file) {
            self::assertStringNotContainsString('203.0.113.77', basename($file));
            self::assertStringNotContainsString('203.0.113.77', (string) file_get_contents($file));
        }
    }

    public function testExactlyOneFilePerIdentifier(): void
    {
        $limiter = new RateLimiter($this->dir, 5, 60);
        $limiter->isAllowed('1.2.3.4');
        $limiter->isAllowed('1.2.3.4');

        self::assertCount(1, glob($this->dir . '/*') ?: [], 'no separate .lock file should be left behind');
    }

    public function testRetryAfterIsTheWholeWindowForAnUntouchedIdentifier(): void
    {
        self::assertSame(60, (new RateLimiter($this->dir, 1, 60))->retryAfter('1.2.3.4'));
    }

    public function testRetryAfterDoesNotCreateABucket(): void
    {
        (new RateLimiter($this->dir, 1, 60))->retryAfter('1.2.3.4');

        self::assertCount(0, glob($this->dir . '/*') ?: []);
    }

    /**
     * A one-hour window would otherwise tell a caller to wait an hour when a
     * few seconds remain.
     */
    public function testRetryAfterCountsDownFromTheOldestHit(): void
    {
        $limiter = new RateLimiter($this->dir, 1, 4);

        self::assertTrue($limiter->isAllowed('1.2.3.4'));
        self::assertFalse($limiter->isAllowed('1.2.3.4'));
        self::assertSame(4, $limiter->retryAfter('1.2.3.4'));

        sleep(2);

        self::assertSame(2, $limiter->retryAfter('1.2.3.4'));
    }

    public function testRetryAfterIsNeverBelowOne(): void
    {
        $limiter = new RateLimiter($this->dir, 1, 1);
        $limiter->isAllowed('1.2.3.4');

        sleep(1);

        self::assertSame(1, $limiter->retryAfter('1.2.3.4'));
    }

    public function testRefundReleasesTheMostRecentHit(): void
    {
        $limiter = new RateLimiter($this->dir, 1, 60);

        self::assertTrue($limiter->isAllowed('1.2.3.4'));
        self::assertFalse($limiter->isAllowed('1.2.3.4'));

        $limiter->refund('1.2.3.4');

        self::assertTrue($limiter->isAllowed('1.2.3.4'));
    }

    public function testRefundOnlyReleasesOneHit(): void
    {
        $limiter = new RateLimiter($this->dir, 3, 60);

        $limiter->isAllowed('1.2.3.4');
        $limiter->isAllowed('1.2.3.4');
        $limiter->isAllowed('1.2.3.4');
        self::assertFalse($limiter->isAllowed('1.2.3.4'));

        $limiter->refund('1.2.3.4');

        self::assertTrue($limiter->isAllowed('1.2.3.4'));
        self::assertFalse($limiter->isAllowed('1.2.3.4'));
    }

    public function testRefundOnAnUnchargedIdentifierIsHarmless(): void
    {
        $limiter = new RateLimiter($this->dir, 1, 60);

        $limiter->refund('1.2.3.4');

        self::assertTrue($limiter->isAllowed('1.2.3.4'));
    }
}
