<?php

declare(strict_types=1);

namespace Data2Md\Tests\Unit;

use PHPUnit\Framework\TestCase;

final class EnvironmentTest extends TestCase
{
    public function testPhpVersionMeetsFloor(): void
    {
        self::assertTrue(PHP_VERSION_ID >= 80300, 'PHP 8.3+ required');
    }

    /**
     * @dataProvider requiredExtensions
     */
    public function testRequiredExtensionIsLoaded(string $extension): void
    {
        self::assertTrue(extension_loaded($extension), "missing extension: $extension");
    }

    public static function requiredExtensions(): array
    {
        return [['mbstring'], ['fileinfo'], ['json'], ['pdo_sqlite']];
    }

    /**
     * @dataProvider requiredBinaries
     */
    public function testRequiredBinaryIsExecutable(string $binary): void
    {
        exec('command -v ' . escapeshellarg($binary), $output, $exitCode);
        self::assertSame(0, $exitCode, "missing binary: $binary");
    }

    public static function requiredBinaries(): array
    {
        return [['pdftotext'], ['nice'], ['ionice']];
    }

    public function testProcOpenIsNotDisabled(): void
    {
        self::assertTrue(function_exists('proc_open'));
        self::assertNotFalse(proc_open('true', [], $pipes));
    }
}
