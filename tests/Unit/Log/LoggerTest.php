<?php

declare(strict_types=1);

namespace Data2Md\Tests\Unit\Log;

use Data2Md\Log\Logger;
use PHPUnit\Framework\TestCase;

final class LoggerTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/d2md-log-' . bin2hex(random_bytes(6));
        mkdir($this->dir, 0770, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir . '/*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($this->dir);
    }

    private function logger(int $maxBytes = 10485760): Logger
    {
        return new Logger($this->dir, $maxBytes, false);
    }

    private function readLines(string $channel): array
    {
        $path = $this->dir . '/' . $channel . '.jsonl';
        self::assertFileExists($path);
        return array_values(array_filter(explode("\n", (string) file_get_contents($path))));
    }

    public function testAppEventIsWrittenAsJsonLine(): void
    {
        $this->logger()->app('convert.ok', ['hash' => 'abc123', 'ms' => 42]);

        $lines = $this->readLines('app');
        self::assertCount(1, $lines);

        $entry = json_decode($lines[0], true);
        self::assertSame('convert.ok', $entry['evt']);
        self::assertSame('info', $entry['lvl']);
        self::assertSame('abc123', $entry['hash']);
        self::assertSame(42, $entry['ms']);
        self::assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', $entry['ts']);
    }

    public function testEachEventIsASeparateLine(): void
    {
        $logger = $this->logger();
        $logger->app('a');
        $logger->app('b');

        self::assertCount(2, $this->readLines('app'));
    }

    public function testAuditGoesToItsOwnFileAndNotToApp(): void
    {
        $this->logger()->audit(['hash' => 'abc123', 'original_name' => 'contrato-2026.pdf']);

        $audit = $this->readLines('audit');
        self::assertCount(1, $audit);
        self::assertSame('contrato-2026.pdf', json_decode($audit[0], true)['original_name']);

        self::assertFileDoesNotExist($this->dir . '/app.jsonl');
    }

    public function testAuditFileIsNotGroupWritableOrWorldReadable(): void
    {
        if (DIRECTORY_SEPARATOR !== '/') {
            self::markTestSkipped('POSIX permissions only');
        }
        $this->logger()->audit(['hash' => 'abc123']);

        $mode = fileperms($this->dir . '/audit.jsonl') & 0777;
        self::assertSame(0640, $mode, sprintf('got %o', $mode));
    }

    /** @dataProvider ips */
    public function testIpTruncation(string $input, string $expected): void
    {
        self::assertSame($expected, Logger::truncateIp($input));
    }

    public static function ips(): array
    {
        return [
            ['200.1.2.34',                          '200.1.2.0'],
            ['10.0.0.255',                          '10.0.0.0'],
            ['2001:db8:1234:5678::1',               '2001:db8:1234::'],
            ['::1',                                 '::'],
            ['not-an-ip',                           'invalid'],
        ];
    }

    public function testRotationMovesTheFileAsideOnceOverTheLimit(): void
    {
        $logger = $this->logger(maxBytes: 200);

        for ($i = 0; $i < 20; $i++) {
            $logger->app('convert.ok', ['n' => $i, 'pad' => str_repeat('x', 40)]);
        }

        self::assertFileExists($this->dir . '/app.jsonl');
        self::assertFileExists($this->dir . '/app.jsonl.1');
        self::assertLessThanOrEqual(400, filesize($this->dir . '/app.jsonl'));
    }

    public function testRotationDoesNotDiscardThePreviousGenerationOnASubsequentSmallWrite(): void
    {
        $logger = $this->logger(maxBytes: 200);

        // Loop until app.jsonl.1 appears (first rotation). The triggering
        // write also appends its own line right after, so the live file is
        // already small again by the time the loop stops.
        for ($i = 0; $i < 20 && !file_exists($this->dir . '/app.jsonl.1'); $i++) {
            $logger->app('convert.ok', ['n' => $i, 'pad' => str_repeat('x', 40)]);
        }

        self::assertFileExists($this->dir . '/app.jsonl.1', 'rotation never triggered; test setup is wrong');
        $rotatedBefore  = file_get_contents($this->dir . '/app.jsonl.1');
        $liveSizeBefore = filesize($this->dir . '/app.jsonl');
        self::assertLessThan(200, $liveSizeBefore, 'live file should already be below the limit right after rotating');

        // One more small write while below the limit must append, not rotate
        // again — a spurious second rotation here would discard ".1".
        $logger->app('convert.ok', ['n' => 'extra']);

        self::assertSame($rotatedBefore, file_get_contents($this->dir . '/app.jsonl.1'));
        self::assertGreaterThan($liveSizeBefore, filesize($this->dir . '/app.jsonl'));
    }

    public function testInvalidUtf8InFilenameDoesNotBreakTheJsonLine(): void
    {
        $badName = "invoice-\xFF\xFE-final.pdf";

        $this->logger()->audit(['hash' => 'abc123', 'original_name' => $badName]);

        $lines = $this->readLines('audit');
        self::assertCount(1, $lines);

        $entry = json_decode($lines[0], true);
        self::assertIsArray($entry, 'invalid UTF-8 in a field must not make the line undecodable');
        self::assertSame('abc123', $entry['hash']);
        self::assertArrayHasKey('original_name', $entry);
        self::assertStringContainsString("\u{FFFD}", $entry['original_name']);
    }

    public function testFieldsCannotOverrideReservedKeys(): void
    {
        $this->logger()->app('convert.ok', ['evt' => 'spoofed', 'ts' => 'nope']);

        $entry = json_decode($this->readLines('app')[0], true);
        self::assertSame('convert.ok', $entry['evt']);
        self::assertNotSame('nope', $entry['ts']);
    }

    public function testUnicodeIsPreservedNotEscaped(): void
    {
        $this->logger()->audit(['original_name' => 'relatório anual.pdf']);

        self::assertStringContainsString('relatório anual.pdf', (string) file_get_contents($this->dir . '/audit.jsonl'));
    }
}
