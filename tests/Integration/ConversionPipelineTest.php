<?php

declare(strict_types=1);

namespace Data2Md\Tests\Integration;

use Data2Md\Convert\ConversionPipeline;
use Data2Md\Converter\MarkdownSanitizer;
use Data2Md\Converter\PdfConverter;
use Data2Md\Log\Logger;
use Data2Md\Runtime\ConversionSemaphore;
use Data2Md\Runtime\Process;
use Data2Md\Security\RateLimiter;
use Data2Md\Security\Validator;
use Data2Md\Storage\Database;
use Data2Md\Storage\FileStorage;
use Data2Md\Storage\MappingStorage;
use PHPUnit\Framework\TestCase;

final class ConversionPipelineTest extends TestCase
{
    private const IP = '203.0.113.10';

    private string $root;
    private FileStorage $files;
    private MappingStorage $mappings;
    private RateLimiter $rateLimiter;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/d2md-pipe-' . bin2hex(random_bytes(6));

        foreach (['md', 'tmp', 'logs', 'ratelimit', 'db'] as $sub) {
            mkdir($this->root . '/' . $sub, 0770, true);
        }

        $db = new Database($this->root . '/db/mappings.sqlite');
        $db->migrate();

        $this->files       = new FileStorage($this->root . '/md', $this->root . '/tmp');
        $this->mappings    = new MappingStorage($db);
        $this->rateLimiter = new RateLimiter($this->root . '/ratelimit', 5, 60);
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

    private function pipeline(int $slots = 2, int $queueWait = 0, int $rateLimitMax = 5): ConversionPipeline
    {
        return new ConversionPipeline(
            validator:              new Validator(200 * 1024 * 1024, '200 MB'),
            mappings:               $this->mappings,
            files:                  $this->files,
            semaphore:              new ConversionSemaphore($this->root . '/tmp', $slots, $queueWait),
            rateLimiter:            new RateLimiter($this->root . '/ratelimit', $rateLimitMax, 60),
            logger:                 new Logger($this->root . '/logs', 10_485_760, false),
            converterFactory:       fn (string $format): PdfConverter => new PdfConverter(
                'pdftotext',
                new Process(30),
                new MarkdownSanitizer(),
                $this->root . '/tmp',
            ),
            previewBytes:           8000,
            rateLimitMax:           $rateLimitMax,
            rateLimitWindowSeconds: 60,
            httpUploads:            false,
        );
    }

    /** A staged copy, because accepting an upload moves the file away. */
    private function upload(string $fixture, ?string $asName = null): array
    {
        $source = dirname(__DIR__) . '/fixtures/' . $fixture;
        $staged = $this->root . '/tmp/upload-' . bin2hex(random_bytes(6));

        copy($source, $staged);

        return ['file' => [
            'name'     => $asName ?? $fixture,
            'size'     => (int) filesize($staged),
            'tmp_name' => $staged,
            'error'    => UPLOAD_ERR_OK,
        ]];
    }

    private function rawUpload(string $contents, string $name): array
    {
        $staged = $this->root . '/tmp/upload-' . bin2hex(random_bytes(6));
        file_put_contents($staged, $contents);

        return ['file' => [
            'name'     => $name,
            'size'     => strlen($contents),
            'tmp_name' => $staged,
            'error'    => UPLOAD_ERR_OK,
        ]];
    }

    private function chargedHits(): int
    {
        $total = 0;

        foreach (glob($this->root . '/ratelimit/*.json') ?: [] as $file) {
            $decoded = json_decode((string) file_get_contents($file), true);
            $total  += count($decoded['hits'] ?? []);
        }

        return $total;
    }

    private function slotsFree(int $slots = 2): int
    {
        $free = 0;

        for ($i = 0; $i < $slots; $i++) {
            $handle = fopen($this->root . '/tmp/slot-' . $i . '.lock', 'c');

            if ($handle !== false && flock($handle, LOCK_EX | LOCK_NB)) {
                $free++;
                flock($handle, LOCK_UN);
            }

            if ($handle !== false) {
                fclose($handle);
            }
        }

        return $free;
    }

    private function stagedUploads(): int
    {
        return count(array_filter(
            glob($this->root . '/tmp/*') ?: [],
            static fn (string $p): bool => !str_ends_with($p, '.lock')
        ));
    }

    public function testConvertsAndReportsTheResult(): void
    {
        $outcome = $this->pipeline()->handle($this->upload('sample.pdf'), [], self::IP);

        self::assertTrue($outcome->isSuccess(), (string) $outcome->message);
        self::assertSame(201, $outcome->status);
        self::assertMatchesRegularExpression('/^[a-f0-9]{15}$/', $outcome->payload['hash']);
        self::assertSame('sample.pdf', $outcome->payload['original_name']);
        self::assertSame(1, $outcome->payload['pages']);
        self::assertStringContainsString('Hello data2md', $outcome->payload['preview']);
        self::assertFalse($outcome->payload['preview_truncated']);

        self::assertNotNull($this->mappings->find($outcome->payload['hash']));
        self::assertTrue($this->files->markdownExists($outcome->payload['hash']));
        self::assertSame(1, $this->chargedHits(), 'a real conversion is charged');
        self::assertSame(0, $this->stagedUploads(), 'the uploaded original is not kept');
        self::assertSame(2, $this->slotsFree());
    }

    /**
     * @dataProvider gatesThatMustNotCharge
     */
    public function testRejectionBeforeConversionChargesNothing(
        string $method,
        array $args,
        int $status,
        string $needle,
    ): void {
        $files   = $this->{$method}(...$args);
        $outcome = $this->pipeline()->handle($files, [], self::IP);

        self::assertFalse($outcome->isSuccess());
        self::assertSame($status, $outcome->status);
        self::assertStringContainsString(
            $needle,
            (string) $outcome->message . ' ' . implode(' ', $outcome->errors)
        );
        self::assertSame(0, $this->chargedHits(), 'the quota must be untouched');
        self::assertSame(2, $this->slotsFree(), 'the slot must be released');
    }

    public static function gatesThatMustNotCharge(): array
    {
        return [
            'wrong content'  => ['rawUpload', ['not a pdf at all', 'x.pdf'], 422, 'does not match'],
            'wrong extension' => ['rawUpload', ['not a pdf at all', 'x.txt'], 422, 'extension'],
            'no text layer'  => ['upload', ['image-only.pdf'], 422, 'no text layer'],
        ];
    }

    public function testMissingFileFieldIsRejected(): void
    {
        $outcome = $this->pipeline()->handle([], [], self::IP);

        self::assertSame(400, $outcome->status);
        self::assertStringContainsString('No file provided', (string) $outcome->message);
        self::assertSame(0, $this->chargedHits());
    }

    /** A repeated field turns the expected scalars into arrays. */
    public function testArrayValuedFileFieldIsRejected(): void
    {
        $outcome = $this->pipeline()->handle(
            ['file' => ['name' => ['a.pdf', 'b.pdf'], 'size' => [1, 2], 'tmp_name' => ['x', 'y'], 'error' => [0, 0]]],
            [],
            self::IP
        );

        self::assertSame(400, $outcome->status);
        self::assertStringContainsString('exactly one file', (string) $outcome->message);
    }

    public function testArrayValuedFormatFieldIsRejected(): void
    {
        $outcome = $this->pipeline()->handle(
            $this->upload('sample.pdf'),
            ['format' => ['pdf', 'pdf']],
            self::IP
        );

        self::assertSame(400, $outcome->status);
        self::assertStringContainsString('single string value', (string) $outcome->message);
    }

    public function testUnsupportedFormatIsRejected(): void
    {
        $outcome = $this->pipeline()->handle($this->upload('sample.pdf'), ['format' => 'docx'], self::IP);

        self::assertSame(400, $outcome->status);
        self::assertStringContainsString('Unsupported format', (string) $outcome->message);
        self::assertSame(0, $this->chargedHits());
    }

    public function testQueueFullChargesNothingAndAsksForARetry(): void
    {
        // Hold the only slot; a separate handle on the same file contends with
        // the pipeline's just as another process would.
        $held = fopen($this->root . '/tmp/slot-0.lock', 'c');
        self::assertTrue(flock($held, LOCK_EX | LOCK_NB), 'could not hold the slot');

        $outcome = $this->pipeline(slots: 1)->handle($this->upload('sample.pdf'), [], self::IP);

        flock($held, LOCK_UN);
        fclose($held);

        self::assertSame(503, $outcome->status);
        self::assertArrayHasKey('Retry-After', $outcome->headers);
        self::assertSame(0, $this->chargedHits(), 'a busy server must not spend the caller quota');
    }

    public function testOverQuotaIsRejectedWithACountdown(): void
    {
        $pipeline = $this->pipeline(rateLimitMax: 1);

        self::assertTrue($pipeline->handle($this->upload('sample.pdf'), [], self::IP)->isSuccess());

        $outcome = $pipeline->handle($this->upload('sample.pdf'), [], self::IP);

        self::assertSame(429, $outcome->status);
        self::assertArrayHasKey('Retry-After', $outcome->headers);
        self::assertLessThanOrEqual(60, (int) $outcome->headers['Retry-After']);
        self::assertGreaterThan(0, (int) $outcome->headers['Retry-After']);
        self::assertSame(2, $this->slotsFree());
    }

    /**
     * A document that fails fast is given back, so the caller is not charged for
     * output that never materialised.
     */
    public function testFastFailureIsRefunded(): void
    {
        $outcome = $this->pipeline()->handle($this->upload('corrupt.pdf'), [], self::IP);

        self::assertSame(422, $outcome->status);
        self::assertSame(0, $this->chargedHits(), 'charged, then refunded');
        self::assertSame(0, $this->stagedUploads(), 'the original is removed on failure too');
        self::assertSame(2, $this->slotsFree());
    }

    public function testPreviewIsTruncatedAtALineBreak(): void
    {
        $pipeline = new ConversionPipeline(
            validator:              new Validator(200 * 1024 * 1024, '200 MB'),
            mappings:               $this->mappings,
            files:                  $this->files,
            semaphore:              new ConversionSemaphore($this->root . '/tmp', 2, 0),
            rateLimiter:            $this->rateLimiter,
            logger:                 new Logger($this->root . '/logs', 10_485_760, false),
            converterFactory:       fn (string $f): PdfConverter => new PdfConverter(
                'pdftotext',
                new Process(30),
                new MarkdownSanitizer(),
                $this->root . '/tmp',
            ),
            previewBytes:           12,
            rateLimitMax:           5,
            rateLimitWindowSeconds: 60,
            httpUploads:            false,
        );

        $outcome = $pipeline->handle($this->upload('multi-page.pdf'), [], self::IP);

        self::assertTrue($outcome->isSuccess(), (string) $outcome->message);

        $preview  = $outcome->payload['preview'];
        $markdown = (string) file_get_contents($this->files->markdownPath($outcome->payload['hash']));

        self::assertTrue($outcome->payload['preview_truncated']);
        self::assertLessThanOrEqual(12, strlen($preview));
        self::assertLessThan(strlen($markdown), strlen($preview));
        self::assertStringStartsWith($preview, $markdown, 'the preview is a prefix of the document');
        self::assertStringEndsWith("\n", $markdown[strlen($preview)] . "\n", 'cut back to a line break');
    }

    /**
     * ApiResponse exits, and exit skips an enclosing finally, so the pipeline
     * writing a response would strand the upload and the slot.
     *
     * @dataProvider responseLayerSymbols
     */
    public function testPipelineNeverTouchesTheResponseLayer(string $symbol): void
    {
        self::assertStringNotContainsString(
            $symbol,
            $this->pipelineCodeWithoutComments(),
            "$symbol must not appear in the pipeline"
        );
    }

    public static function responseLayerSymbols(): array
    {
        // "header(" rather than "header": returning header data is the design,
        // calling PHP's header() is what strands the cleanup.
        return [['ApiResponse'], ['http_response_code'], ['header('], ['exit'], ['echo']];
    }

    /** Comments discuss the response layer on purpose; only the code must avoid it. */
    private function pipelineCodeWithoutComments(): string
    {
        $source = (string) file_get_contents(dirname(__DIR__, 2) . '/src/Convert/ConversionPipeline.php');
        $code   = '';

        foreach (token_get_all($source) as $token) {
            if (is_array($token) && in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }

            $code .= is_array($token) ? $token[1] : $token;
        }

        return $code;
    }
}
