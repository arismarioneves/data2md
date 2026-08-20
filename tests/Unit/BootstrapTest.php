<?php

declare(strict_types=1);

namespace Data2Md\Tests\Unit;

use PHPUnit\Framework\TestCase;

use function Data2Md\format_bytes_label;

final class BootstrapTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        require_once dirname(__DIR__, 2) . '/src/helpers.php';
    }

    /** @dataProvider labels */
    public function testLabelIsDerivedFromMegabytes(int $mb, string $expected): void
    {
        self::assertSame($expected, format_bytes_label($mb));
    }

    public static function labels(): array
    {
        return [
            [200,  '200 MB'],
            [500,  '500 MB'],
            [1024, '1 GB'],
            [2048, '2 GB'],
            [1536, '1536 MB'],
            [1,    '1 MB'],
        ];
    }
}
