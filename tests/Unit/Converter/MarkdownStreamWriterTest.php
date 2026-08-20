<?php

declare(strict_types=1);

namespace Data2Md\Tests\Unit\Converter;

use Data2Md\Converter\MarkdownSanitizer;
use Data2Md\Converter\MarkdownStreamWriter;
use PHPUnit\Framework\TestCase;

final class MarkdownStreamWriterTest extends TestCase
{
    /** @return array{0:string,1:\Data2Md\Converter\SanitizeStats} */
    private function stream(string $input): array
    {
        $out    = fopen('php://memory', 'r+');
        $writer = new MarkdownStreamWriter($out, new MarkdownSanitizer());

        foreach (explode("\n", str_replace(["\r\n", "\r"], "\n", $input)) as $line) {
            $writer->writeLine($line);
        }

        $stats = $writer->finish();

        rewind($out);
        $result = (string) stream_get_contents($out);
        fclose($out);

        return [$result, $stats];
    }

    /** @dataProvider corpus */
    public function testStreamingMatchesTheInMemorySanitizer(string $input): void
    {
        [$streamed] = $this->stream($input);
        $inMemory   = (new MarkdownSanitizer())->clean($input);

        self::assertSame($inMemory, $streamed, 'streaming must not change the heuristic output');
    }

    public static function corpus(): array
    {
        return [
            'plain paragraph'   => ["Hello world.\nSecond line."],
            'blank collapsing'  => ["A\n\n\n\n\nB"],
            'leading blanks'    => ["\n\n\nA"],
            'trailing blanks'   => ["A\n\n\n"],
            'headings kept'     => ["## Page 1\n\nSome real text here."],
            'list kept'         => ["- item one\n- item two"],
            'hr kept'           => ["Text\n\n---\n\nMore text"],
            'cmap garbage'      => ["Real sentence here.\neJERFGMEMfMfeJNFEQ\nGSVWJJGTGMUEN\nRFGMEMfMfXY\nBack to real text."],
            'symbol noise'      => ["Real sentence here.\n;<='& >??#A\n,\"#\$%&'()\nYZ[\\]^_`abcd\nBack to real text."],
            'numeric noise'     => ["Intro line here.\n2! D!2A2 D      D!2A2 >D2A D\n1! A!1B1 A     A!1B1 >A1B\n3! C!3D3 C     C!3D3 >C3D\nOutro line here."],
            'url line kept'     => ["Visit https://example.com/very/long/path/here for details"],
            'control chars'     => ["Line with \x00null and \x0bvtab\nSecond line"],
            'zero width'        => ["Text with \u{200B}zero width\u{FEFF} chars"],
            'unicode text'      => ["Relatório anual de operações.\nApêndice às demonstrações."],
            'single garbage'    => ["Real text.\neJERFGMEMfMfeJNFEQ\nMore real text."],
            'two garbage'       => ["Real text.\neJERFGMEMfMfeJNFEQ\nGSVWJJGTGMUEN\nMore real text."],
            'garbage at end'    => ["Real text.\neJERFGMEMfMfeJNFEQ\nGSVWJJGTGMUEN\nRFGMEMfMfXY"],
            'garbage at start'  => ["eJERFGMEMfMfeJNFEQ\nGSVWJJGTGMUEN\nRFGMEMfMfXY\nReal text."],
            'empty input'       => [''],
            'only blanks'       => ["\n\n\n"],
            'only garbage'      => ["eJERFGMEMfMfeJNFEQ\nGSVWJJGTGMUEN\nRFGMEMfMfXY"],
        ];
    }

    public function testStatsCountLinesAndCharacters(): void
    {
        [$output, $stats] = $this->stream("Hello world.\nSecond line.");

        self::assertSame(mb_strlen($output), $stats->characters);
        self::assertSame(substr_count($output, "\n") + 1, $stats->lines);
    }

    public function testStatsOnEmptyOutput(): void
    {
        [, $stats] = $this->stream('');

        self::assertSame(0, $stats->characters);
        self::assertSame(0, $stats->lines);
    }

    public function testWriteRawBypassesTheHeuristic(): void
    {
        $out    = fopen('php://memory', 'r+');
        $writer = new MarkdownStreamWriter($out, new MarkdownSanitizer());

        // This line would be dropped as garbage if it went through writeLine.
        $writer->writeRaw('eJERFGMEMfMfeJNFEQ');
        $writer->finish();

        rewind($out);
        self::assertStringContainsString('eJERFGMEMfMfeJNFEQ', (string) stream_get_contents($out));
        fclose($out);
    }

    public function testMemoryStaysFlatOnALargeDocument(): void
    {
        // php://temp spills to disk past 2 MB, so the destination buffer
        // drops out of the measurement and $growth reflects only the
        // writer's own state.
        $out    = fopen('php://temp', 'r+');
        $writer = new MarkdownStreamWriter($out, new MarkdownSanitizer());

        $before = memory_get_usage(true);

        for ($i = 0; $i < 200_000; $i++) {
            $writer->writeLine('This is a perfectly ordinary line of document text.');
        }
        $writer->finish();

        $growth = memory_get_usage(true) - $before;
        fclose($out);

        self::assertLessThan(2 * 1024 * 1024, $growth, 'writer must not accumulate the document');
    }
}
