<?php

declare(strict_types=1);

namespace Data2Md\Tests\Unit\View;

use PHPUnit\Framework\TestCase;

/**
 * Validator::sanitizeOriginalName() strips control characters and path
 * separators and says outright that escaping is the view's job. The CSP on
 * these pages allows 'unsafe-inline', so an event handler in a filename would
 * run: "<img src=x onerror=...>.pdf" reaches the database intact.
 */
final class RenderedFilenameTest extends TestCase
{
    public function testEveryRenderedFilenameGoesThroughTheEscaper(): void
    {
        $unescaped = [];

        foreach (glob(dirname(__DIR__, 3) . '/public/*.php') ?: [] as $file) {
            foreach (file($file) ?: [] as $index => $line) {
                if (!str_contains($line, 'original_name')) {
                    continue;
                }

                // Only lines that write to the page.
                if (!str_contains($line, '<?=') && !str_contains($line, 'echo ')) {
                    continue;
                }

                if (!str_contains($line, 'e(')) {
                    $unescaped[] = basename($file) . ':' . ($index + 1) . ' ' . trim($line);
                }
            }
        }

        self::assertSame([], $unescaped, "filenames rendered without e():\n" . implode("\n", $unescaped));
    }

    /** @dataProvider payloads */
    public function testEscaperNeutralisesTheAttack(string $filename, string $dangerous): void
    {
        require_once dirname(__DIR__, 3) . '/src/View/helpers.php';

        self::assertStringNotContainsString($dangerous, e($filename));
    }

    public static function payloads(): array
    {
        return [
            'event handler'  => ['<img src=x onerror=alert(1)>.pdf', '<img'],
            'script tag'     => ['<script>alert(1)<script>.pdf', '<script'],
            'attribute break' => ['" onmouseover="alert(1)".pdf', '"'],
            'single quote'   => ["' onmouseover='alert(1)'.pdf", "'"],
        ];
    }
}
