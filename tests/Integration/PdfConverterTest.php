<?php

declare(strict_types=1);

namespace Data2Md\Tests\Integration;

use Data2Md\Converter\ConversionFailedException;
use Data2Md\Converter\ConversionTimeoutException;
use Data2Md\Converter\MarkdownSanitizer;
use Data2Md\Converter\NoTextLayerException;
use Data2Md\Converter\PdfConverter;
use Data2Md\Runtime\Process;
use PHPUnit\Framework\TestCase;

final class PdfConverterTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/d2md-conv-' . bin2hex(random_bytes(6));
        mkdir($this->tmpDir, 0770, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->tmpDir . '/*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($this->tmpDir);
    }

    private function converter(int $timeout = 30): PdfConverter
    {
        return new PdfConverter(
            'pdftotext',
            new Process($timeout),
            new MarkdownSanitizer(),
            $this->tmpDir,
        );
    }

    private function fixture(string $name): string
    {
        return dirname(__DIR__) . '/fixtures/' . $name;
    }

    public function testConvertsAPdfWithATextLayer(): void
    {
        $mdPath = $this->tmpDir . '/out.md';

        $result = $this->converter()->convertToFile($this->fixture('sample.pdf'), $mdPath);

        $markdown = (string) file_get_contents($mdPath);

        self::assertStringContainsString('Hello data2md', $markdown);
        self::assertStringContainsString('Second line of text', $markdown);
        self::assertStringContainsString('## Page 1', $markdown);
        self::assertSame(1, $result->pages);
        self::assertGreaterThan(0, $result->characters);
        self::assertGreaterThan(0, $result->lines);
    }

    public function testStatsMatchTheFileOnDisk(): void
    {
        $mdPath = $this->tmpDir . '/out.md';

        $result   = $this->converter()->convertToFile($this->fixture('sample.pdf'), $mdPath);
        $markdown = (string) file_get_contents($mdPath);

        self::assertSame(mb_strlen($markdown), $result->characters);
        self::assertSame(substr_count($markdown, "\n") + 1, $result->lines);
    }

    public function testImageOnlyPdfIsRejectedWithAClearError(): void
    {
        $this->expectException(NoTextLayerException::class);

        $this->converter()->convertToFile($this->fixture('image-only.pdf'), $this->tmpDir . '/out.md');
    }

    public function testNoMarkdownIsLeftBehindWhenConversionFails(): void
    {
        $mdPath = $this->tmpDir . '/out.md';

        try {
            $this->converter()->convertToFile($this->fixture('image-only.pdf'), $mdPath);
        } catch (NoTextLayerException) {
            // expected
        }

        self::assertFileDoesNotExist($mdPath);
    }

    public function testCorruptPdfRaisesConversionFailed(): void
    {
        $this->expectException(ConversionFailedException::class);

        $this->converter()->convertToFile($this->fixture('corrupt.pdf'), $this->tmpDir . '/out.md');
    }

    public function testMissingInputRaisesConversionFailed(): void
    {
        $this->expectException(ConversionFailedException::class);

        $this->converter()->convertToFile($this->tmpDir . '/nope.pdf', $this->tmpDir . '/out.md');
    }

    public function testTimeoutRaisesConversionTimeout(): void
    {
        // A real pdftotext rejects the arguments PdfConverter appends before it
        // would ever hang, so the binary under test must be a stub that ignores
        // its arguments and sleeps long enough to trigger the deadline.
        $fakeBin = $this->tmpDir . '/slow-extractor';
        file_put_contents($fakeBin, "#!/bin/sh\nsleep 30\n");
        chmod($fakeBin, 0755);

        $converter = new PdfConverter(
            $fakeBin,
            new Process(1),
            new MarkdownSanitizer(),
            $this->tmpDir,
        );

        $this->expectException(ConversionTimeoutException::class);

        $started = microtime(true);

        try {
            $converter->convertToFile($this->fixture('sample.pdf'), $this->tmpDir . '/out.md');
        } finally {
            self::assertLessThan(10.0, microtime(true) - $started);
        }
    }

    public function testNoTemporaryFilesAreLeftBehind(): void
    {
        $this->converter()->convertToFile($this->fixture('sample.pdf'), $this->tmpDir . '/out.md');

        $leftovers = array_filter(
            glob($this->tmpDir . '/*') ?: [],
            static fn (string $f): bool => !str_ends_with($f, '.md')
        );

        self::assertSame([], array_values($leftovers));
    }

    public function testSupportedTypes(): void
    {
        $converter = $this->converter();

        self::assertSame('pdf', $converter->getFormat());
        self::assertSame(['pdf'], $converter->getSupportedExtensions());
        self::assertSame(['application/pdf'], $converter->getSupportedMimeTypes());
    }
}
