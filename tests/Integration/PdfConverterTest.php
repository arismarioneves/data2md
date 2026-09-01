<?php

declare(strict_types=1);

namespace Data2Md\Tests\Integration;

use Data2Md\Converter\ConversionFailedException;
use Data2Md\Converter\ConversionTimeoutException;
use Data2Md\Converter\MarkdownSanitizer;
use Data2Md\Converter\NoTextLayerException;
use Data2Md\Converter\PdfConverter;
use Data2Md\Runtime\Process;
use Data2Md\Tests\Integration\Support\FailingWriteStream;
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

    /**
     * @dataProvider probeExpectations
     */
    public function testProbeDetectsWhetherAFullRunIsWorthwhile(string $fixture, bool $expected, string $why): void
    {
        self::assertSame($expected, $this->converter()->probe($this->fixture($fixture)), $why);
    }

    public static function probeExpectations(): array
    {
        return [
            'text'              => ['sample.pdf', true, 'has a text layer'],
            'multi page'        => ['multi-page.pdf', true, 'has a text layer'],
            'blank middle page' => ['blank-middle-page.pdf', true, 'text on the surrounding pages'],
            'image only'        => ['image-only.pdf', false, 'nothing for a full run to extract'],
            // pdftotext exits non-zero here; the full run reports the specific
            // failure, so the probe must not swallow it as "no text".
            'corrupt'           => ['corrupt.pdf', true, 'defers to the full run'],
        ];
    }

    /** pdftotext emits a form feed per page, which is not content. */
    public function testProbeTreatsPageBreaksAsEmpty(): void
    {
        self::assertFalse($this->converter()->probe($this->fixture('image-only.pdf')));
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

    public function testFailureMessageDoesNotLeakFilesystemPaths(): void
    {
        $inputPath = $this->tmpDir . '/nope.pdf';

        try {
            $this->converter()->convertToFile($inputPath, $this->tmpDir . '/out.md');
            self::fail('Expected a ConversionFailedException.');
        } catch (ConversionFailedException $e) {
            self::assertStringNotContainsString($inputPath, $e->getMessage());
            self::assertStringNotContainsString($this->tmpDir, $e->getMessage());

            // The diagnostic is relocated, not dropped: it stays available to
            // whoever catches the exception, just not in the message an API
            // client would see.
            self::assertNotNull($e->detail());
            self::assertStringContainsString($inputPath, (string) $e->detail());
        }
    }

    public function testPartialMarkdownIsRemovedWhenAWriteFails(): void
    {
        FailingWriteStream::register();

        $backingPath = $this->tmpDir . '/backing.md';

        // Let the first write (the "## Page 1" header) succeed, then fail
        // every write after it, simulating an I/O error partway through.
        FailingWriteStream::$allowedWrites = 1;

        try {
            $this->converter()->convertToFile(
                $this->fixture('sample.pdf'),
                FailingWriteStream::url($backingPath)
            );
            self::fail('Expected the simulated write failure to propagate.');
        } catch (\Throwable) {
            // expected: some write failed partway through rendering
        }

        self::assertFileDoesNotExist($backingPath);
    }

    public function testMultiPagePdfProducesPagesInOrder(): void
    {
        $mdPath = $this->tmpDir . '/out.md';

        $result   = $this->converter()->convertToFile($this->fixture('multi-page.pdf'), $mdPath);
        $markdown = (string) file_get_contents($mdPath);

        self::assertSame(3, $result->pages);

        $page1 = strpos($markdown, '## Page 1');
        $page2 = strpos($markdown, '## Page 2');
        $page3 = strpos($markdown, '## Page 3');
        $one   = strpos($markdown, 'Page one content here');
        $two   = strpos($markdown, 'Page two content here');
        $three = strpos($markdown, 'Page three content here');

        self::assertNotFalse($page1);
        self::assertNotFalse($page2);
        self::assertNotFalse($page3);
        self::assertNotFalse($one);
        self::assertNotFalse($two);
        self::assertNotFalse($three);

        self::assertTrue($page1 < $one && $one < $page2, 'page 1 text must sit under its own header');
        self::assertTrue($page2 < $two && $two < $page3, 'page 2 text must sit under its own header');
        self::assertTrue($page3 < $three, 'page 3 text must sit under its own header');
    }

    public function testPageWithNoContentDoesNotGetItsOwnNumber(): void
    {
        $mdPath = $this->tmpDir . '/out.md';

        $result   = $this->converter()->convertToFile($this->fixture('blank-middle-page.pdf'), $mdPath);
        $markdown = (string) file_get_contents($mdPath);

        // The fixture has 3 physical pages; the middle one has no content
        // stream operators, so pdftotext emits it as two form feeds with
        // nothing between them. A page is only counted once non-blank
        // content is seen after it opens, so the blank page gets no header
        // at all and the third physical page is numbered "## Page 2".
        self::assertSame(2, $result->pages);
        self::assertStringContainsString('## Page 1', $markdown);
        self::assertStringContainsString('## Page 2', $markdown);
        self::assertStringNotContainsString('## Page 3', $markdown);

        $page1 = strpos($markdown, '## Page 1');
        $page2 = strpos($markdown, '## Page 2');
        $first = strpos($markdown, 'First page content');
        $third = strpos($markdown, 'Third page content');

        self::assertNotFalse($first);
        self::assertNotFalse($third);
        self::assertTrue($page1 < $first && $first < $page2);
        self::assertTrue($page2 < $third);
    }
}
