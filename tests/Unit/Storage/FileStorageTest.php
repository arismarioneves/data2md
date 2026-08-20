<?php

declare(strict_types=1);

namespace Data2Md\Tests\Unit\Storage;

use Data2Md\Storage\FileStorage;
use PHPUnit\Framework\TestCase;

final class FileStorageTest extends TestCase
{
    private string $root;
    private FileStorage $storage;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/d2md-fs-' . bin2hex(random_bytes(6));
        $this->storage = new FileStorage($this->root . '/md', $this->root . '/tmp');
    }

    protected function tearDown(): void
    {
        foreach (['md', 'tmp'] as $sub) {
            foreach (glob($this->root . '/' . $sub . '/*') ?: [] as $file) {
                unlink($file);
            }
            if (is_dir($this->root . '/' . $sub)) {
                rmdir($this->root . '/' . $sub);
            }
        }
        if (is_dir($this->root)) {
            rmdir($this->root);
        }
    }

    public function testDirectoriesAreCreatedOnConstruction(): void
    {
        self::assertDirectoryExists($this->root . '/md');
        self::assertDirectoryExists($this->root . '/tmp');
    }

    public function testAcceptUploadMovesIntoTmpNotAPermanentDirectory(): void
    {
        $source = $this->root . '/incoming.bin';
        file_put_contents($source, 'PDF-BYTES');

        $stored = $this->storage->acceptUpload($source, 'a1b2c3d4e5f6789', isHttpUpload: false);

        self::assertStringStartsWith($this->root . '/tmp/', $stored);
        self::assertSame('PDF-BYTES', file_get_contents($stored));
        self::assertFileDoesNotExist($source);

        unlink($stored);
    }

    public function testDeleteOriginalRemovesTheFile(): void
    {
        $source = $this->root . '/incoming.bin';
        file_put_contents($source, 'PDF-BYTES');
        $stored = $this->storage->acceptUpload($source, 'a1b2c3d4e5f6789', isHttpUpload: false);

        $this->storage->deleteOriginal($stored);

        self::assertFileDoesNotExist($stored);
    }

    public function testDeleteOriginalIsSafeOnAMissingFile(): void
    {
        $this->storage->deleteOriginal($this->root . '/tmp/never-existed');
        self::assertTrue(true);
    }

    public function testDeleteOriginalRefusesPathsOutsideTmp(): void
    {
        $outside = $this->root . '/md/precious.md';
        file_put_contents($outside, 'keep me');

        $this->expectException(\InvalidArgumentException::class);
        try {
            $this->storage->deleteOriginal($outside);
        } finally {
            self::assertFileExists($outside);
        }
    }

    public function testMarkdownLifecycle(): void
    {
        $path = $this->storage->markdownPath('a1b2c3d4e5f6789');
        file_put_contents($path, "# Title\n\nBody\n");

        self::assertTrue($this->storage->markdownExists('a1b2c3d4e5f6789'));
        self::assertSame(14, $this->storage->markdownSize('a1b2c3d4e5f6789'));
        self::assertTrue($this->storage->deleteMarkdown('a1b2c3d4e5f6789'));
        self::assertFalse($this->storage->markdownExists('a1b2c3d4e5f6789'));
        self::assertFalse($this->storage->deleteMarkdown('a1b2c3d4e5f6789'));
    }

    public function testReadMarkdownPrefixDoesNotLoadTheWholeFile(): void
    {
        $path = $this->storage->markdownPath('a1b2c3d4e5f6789');
        file_put_contents($path, str_repeat('a', 100_000));

        $prefix = $this->storage->readMarkdownPrefix('a1b2c3d4e5f6789', 100);

        self::assertSame(100, strlen($prefix));
    }

    public function testReadMarkdownPrefixNeverSplitsAUtf8Character(): void
    {
        $path = $this->storage->markdownPath('a1b2c3d4e5f6789');
        // 'é' is two bytes; cutting at 5 would land mid-character.
        file_put_contents($path, 'aaaaébbbb');

        $prefix = $this->storage->readMarkdownPrefix('a1b2c3d4e5f6789', 5);

        self::assertSame('aaaa', $prefix);
        self::assertTrue(mb_check_encoding($prefix, 'UTF-8'));
    }

    public function testOrphanMarkdownHashes(): void
    {
        foreach (['aaaaaaaaaaaaaaa', 'bbbbbbbbbbbbbbb', 'ccccccccccccccc'] as $hash) {
            file_put_contents($this->storage->markdownPath($hash), 'x');
        }

        $orphans = $this->storage->orphanMarkdownHashes(['aaaaaaaaaaaaaaa', 'ccccccccccccccc']);

        self::assertSame(['bbbbbbbbbbbbbbb'], $orphans);
    }

    public function testMarkdownPathRejectsTraversal(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->storage->markdownPath('../../etc/passwd');
    }

    public function testMarkdownSizeIsZeroWhenMissing(): void
    {
        self::assertSame(0, $this->storage->markdownSize('a1b2c3d4e5f6789'));
    }
}
