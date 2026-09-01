<?php

declare(strict_types=1);

namespace Data2Md\Tests\Unit\Storage;

use Data2Md\Storage\StorageIdentity;
use PHPUnit\Framework\TestCase;

final class StorageIdentityTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/d2md-ident-' . bin2hex(random_bytes(6));
        mkdir($this->dir . '/db', 0750, true);
    }

    protected function tearDown(): void
    {
        // The marker is a dotfile, which glob('*') does not match.
        $files = array_merge(
            glob($this->dir . '/db/*') ?: [],
            glob($this->dir . '/db/.[!.]*') ?: []
        );

        foreach ($files as $file) {
            unlink($file);
        }

        foreach ([$this->dir . '/db', $this->dir] as $path) {
            if (is_dir($path)) {
                rmdir($path);
            }
        }
    }

    /** The state a deploy onto an unmounted volume lands in. */
    public function testAnEmptyDirectoryReportsItselfAsNew(): void
    {
        $result = (new StorageIdentity($this->dir))->markAndRead(1_000);

        self::assertTrue($result['fresh']);
        self::assertSame(1_000, $result['since']);
        self::assertMatchesRegularExpression('/^[0-9a-f]{16}$/', $result['id']);
    }

    /** The whole point: the second boot has to recognise the first one. */
    public function testASecondBootRecognisesTheSameDirectory(): void
    {
        $identity = new StorageIdentity($this->dir);

        $first  = $identity->markAndRead(1_000);
        $second = $identity->markAndRead(9_999);

        self::assertFalse($second['fresh']);
        self::assertSame($first['id'], $second['id']);
        self::assertSame(1_000, $second['since'], 'the original date survives, not the current one');
    }

    public function testTheMarkerIsWrittenWhereItLives(): void
    {
        (new StorageIdentity($this->dir))->markAndRead(1_000);

        self::assertFileExists($this->dir . '/db/.storage-id');
    }

    /** A truncated or hand-edited marker must not read as persistent. */
    public function testAMalformedMarkerIsTreatedAsNew(): void
    {
        foreach (['', 'garbage', 'zzzz 1000', 'abc123 notatime', '0123456789abcdef'] as $content) {
            file_put_contents($this->dir . '/db/.storage-id', $content);

            $result = (new StorageIdentity($this->dir))->markAndRead(2_000);

            self::assertTrue($result['fresh'], "marker " . var_export($content, true) . ' should not pass');
        }
    }

    /** Rewriting a broken marker must leave a readable one behind. */
    public function testAMalformedMarkerIsReplacedWithAValidOne(): void
    {
        file_put_contents($this->dir . '/db/.storage-id', 'garbage');

        $identity = new StorageIdentity($this->dir);
        $identity->markAndRead(2_000);

        self::assertFalse($identity->markAndRead(3_000)['fresh']);
    }

    /** A directory it cannot write to must report, not throw. */
    public function testAnUnwritableDirectoryStillReports(): void
    {
        $result = (new StorageIdentity($this->dir . '/does-not-exist'))->markAndRead(1_000);

        self::assertTrue($result['fresh']);
    }
}
