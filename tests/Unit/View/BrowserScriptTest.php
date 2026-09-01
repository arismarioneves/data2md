<?php

declare(strict_types=1);

namespace Data2Md\Tests\Unit\View;

use PHPUnit\Framework\TestCase;

/**
 * Parses the JavaScript that ships to the browser.
 *
 * Nothing else in this suite loads these files, so a syntax error in one is
 * invisible to every other test while the converter is broken in every browser.
 * That is not hypothetical: a bad edit spliced a function definition into the
 * middle of a setInterval call, the whole PHP suite stayed green, and the file
 * reached production.
 *
 * Brace and paren counting cannot stand in for this -- the corruption above was
 * perfectly balanced.
 */
final class BrowserScriptTest extends TestCase
{
    private static function node(): string
    {
        foreach (['node', 'nodejs'] as $binary) {
            exec('command -v ' . escapeshellarg($binary) . ' 2>/dev/null', $out, $code);

            if ($code === 0) {
                return $binary;
            }
        }

        self::markTestSkipped('node is not installed; rebuild the dev image');
    }

    /** @dataProvider scripts */
    public function testItParses(string $relative): void
    {
        $path = dirname(__DIR__, 3) . '/' . $relative;

        self::assertFileExists($path);

        exec(
            sprintf('%s --check %s 2>&1', self::node(), escapeshellarg($path)),
            $output,
            $exitCode
        );

        self::assertSame(0, $exitCode, "$relative does not parse:\n" . implode("\n", $output));
    }

    public static function scripts(): array
    {
        return [
            ['public/assets/js/app.js'],
        ];
    }

    /**
     * The corruption that shipped replaced `function (` with a named function
     * declaration everywhere at once. It parsed as garbage, but it would also
     * have been caught by noticing the same helper defined eleven times.
     */
    public function testNoHelperIsDefinedMoreThanOnce(): void
    {
        $source = (string) file_get_contents(dirname(__DIR__, 3) . '/public/assets/js/app.js');

        preg_match_all('/^function\s+([A-Za-z0-9_$]+)\s*\(/m', $source, $matches);

        $counts = array_count_values($matches[1]);

        foreach ($counts as $name => $count) {
            self::assertSame(1, $count, "function $name() is declared $count times");
        }
    }
}
