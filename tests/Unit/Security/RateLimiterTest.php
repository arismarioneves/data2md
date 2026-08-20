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

    public function testRetryAfterMatchesTheWindow(): void
    {
        self::assertSame(60, (new RateLimiter($this->dir, 1, 60))->retryAfter());
    }
}
