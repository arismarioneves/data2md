<?php

declare(strict_types=1);

namespace Data2Md\Tests\Unit\Converter;

use Data2Md\Converter\MarkdownSanitizer;
use Data2Md\Converter\MarkdownStreamWriter;
use PHPUnit\Framework\TestCase;

final class MarkdownStreamWriterTest extends TestCase
{
    /**
     * Feeds $input the way PdfConverter::render() actually does: split only
     * on "\n" (what fgets() stops at), then rtrim() each piece of a
     * trailing "\r\n" or "\r". An interior "\r" — one not immediately
     * followed by "\n" — is deliberately NOT normalised here, because
     * production does not normalise it either; it survives into the string
     * handed to writeLine(). Pre-splitting on every "\r" here (as an
     * earlier version of this harness did) would hide exactly the input
     * class where the streaming writer and clean() can diverge — see
     * testInteriorCarriageReturnDivergesFromClean().
     *
     * @return array{0:string,1:\Data2Md\Converter\SanitizeStats}
     */
    private function stream(string $input): array
    {
        $out    = fopen('php://memory', 'r+');
        $writer = new MarkdownStreamWriter($out, new MarkdownSanitizer());

        foreach (explode("\n", $input) as $rawLine) {
            $writer->writeLine(rtrim($rawLine, "\r\n"));
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

            // isGarbage() returns true for each of these three lines (unlike
            // eJERFGMEMfMfeJNFEQ above), so they actually drive skipRun to 3
            // at the very start of the document.
            'real garbage at start, text after' => ["GSVWJJGTGMUEN\nRFGMEMfMfXY\n;<='& >??#A\nReal text."],
            'real garbage only'                 => ["GSVWJJGTGMUEN\nRFGMEMfMfXY\n;<='& >??#A"],
            'real garbage at start and end'      => ["GSVWJJGTGMUEN\nRFGMEMfMfXY\n;<='& >??#A\nReal text.\nGSVWJJGTGMUEN\nRFGMEMfMfXY\n;<='& >??#A"],
            'two real garbage lines, no marker'  => ["GSVWJJGTGMUEN\nRFGMEMfMfXY\nReal text."],
        ];
    }

    /**
     * Production reads with fgets(), which only breaks lines on "\n";
     * rtrim($rawLine, "\r\n") strips a trailing "\r" but not one in the
     * middle. An interior "\r" therefore reaches writeLine() still joined
     * to the text after it — stripControlChars() then turns it into an
     * embedded "\n" without re-splitting, so isGarbage() judges the whole
     * two-part blob as a single line. clean() does not have this problem:
     * it normalises every "\r" to "\n" over the whole document *before*
     * splitting on "\n", so the same two parts become two independent
     * lines there.
     *
     * Verified empirically, and the divergence is worse than cosmetic: for
     * "Real text.\rGSVWJJGTGMUEN", clean() drops only the garbage half and
     * keeps "Real text." — but in the merged blob, the long consonant run
     * from "GSVWJJGTGMUEN" (rule 5 in isGarbage()) still matches, so
     * isGarbage() reports the *whole* line as garbage and the streaming
     * writer silently drops "Real text." too. This is a real content-loss
     * bug for any source line broken by a bare \r not followed by \n, not
     * merely a formatting difference — flagging for a decision rather than
     * changing the heuristic here.
     */
    public function testInteriorCarriageReturnDivergesFromClean(): void
    {
        $input = "Real text.\rGSVWJJGTGMUEN";

        [$streamed] = $this->stream($input);
        $inMemory   = (new MarkdownSanitizer())->clean($input);

        self::assertSame('Real text.', $inMemory, 'clean() keeps the real half and drops the garbage half');
        self::assertSame('', $streamed, 'the streaming writer currently drops the whole merged line, real text included');
        self::assertNotSame($inMemory, $streamed, 'the two implementations diverge on an interior carriage return');
    }

    public function testStreamingMatchesTheInMemorySanitizerOnRandomDocuments(): void
    {
        $sanitizer = new MarkdownSanitizer();

        $garbageLines = ['GSVWJJGTGMUEN', 'RFGMEMfMfXY', ";<='& >??#A"];
        foreach ($garbageLines as $line) {
            self::assertTrue($sanitizer->isGarbage($line), "pool line must be garbage: $line");
        }

        $nonGarbageTrap = 'eJERFGMEMfMfeJNFEQ';
        self::assertFalse($sanitizer->isGarbage($nonGarbageTrap), 'pool trap line must not be garbage');

        $pool = array_merge($garbageLines, [
            'Real sentence about ordinary things.',
            'Another perfectly plain line of text.',
            '',
            '## Section heading',
            '- a list item',
            '---',
            $nonGarbageTrap,
        ]);

        mt_srand(424242);

        for ($doc = 0; $doc < 300; $doc++) {
            $lines = [];
            for ($i = 0, $count = mt_rand(0, 9); $i < $count; $i++) {
                $lines[] = $pool[mt_rand(0, count($pool) - 1)];
            }
            $input = implode("\n", $lines);

            $expected = $sanitizer->clean($input);
            [$actual] = $this->stream($input);

            self::assertSame($expected, $actual, sprintf(
                "input: %s\nexpected: %s\nactual: %s",
                var_export($input, true),
                var_export($expected, true),
                var_export($actual, true)
            ));
        }
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

    public function testWriteRawEmptyLineIsBufferedAsABlank(): void
    {
        $out    = fopen('php://memory', 'r+');
        $writer = new MarkdownStreamWriter($out, new MarkdownSanitizer());

        $writer->writeLine('Real text.');
        $writer->blankLine();
        $writer->writeRaw('');
        $writer->writeLine('More text.');
        $writer->finish();

        rewind($out);
        self::assertSame("Real text.\n\nMore text.", (string) stream_get_contents($out));
        fclose($out);
    }

    public function testWriteRawEmptyLineAtStartLeavesNoLeadingBlank(): void
    {
        $out    = fopen('php://memory', 'r+');
        $writer = new MarkdownStreamWriter($out, new MarkdownSanitizer());

        $writer->writeRaw('');
        $writer->writeLine('Real text.');
        $writer->finish();

        rewind($out);
        self::assertSame('Real text.', (string) stream_get_contents($out));
        fclose($out);
    }

    public function testWriteRawInterleavedWithWriteLineAndBlankLine(): void
    {
        $out    = fopen('php://memory', 'r+');
        $writer = new MarkdownStreamWriter($out, new MarkdownSanitizer());

        $writer->writeRaw('## Page 1');
        $writer->blankLine();
        $writer->writeLine('Real content.');
        $writer->blankLine();
        $writer->writeRaw('## Page 2');
        $writer->blankLine();
        $writer->writeLine('More content.');
        $writer->finish();

        rewind($out);
        self::assertSame(
            "## Page 1\n\nReal content.\n\n## Page 2\n\nMore content.",
            (string) stream_get_contents($out)
        );
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
