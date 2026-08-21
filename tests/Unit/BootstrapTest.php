<?php

declare(strict_types=1);

namespace Data2Md\Tests\Unit;

use Data2Md\Config\Config;
use PHPUnit\Framework\TestCase;

use function Data2Md\format_bytes_label;
use function Data2Md\request_ceiling_seconds;

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

    /**
     * Pins the formula shared by src/bootstrap.php's max_execution_time and
     * public/app.php's client-side upload timeout.
     */
    public function testRequestCeilingIsTimeoutPlusQueueWaitPlusSixty(): void
    {
        $config = Config::fromArray([
            'APP_ENV'                    => 'production',
            'APP_URL'                    => 'https://data2md.example.com',
            'APP_NAME'                   => 'data2md',
            'APP_VERSION'                => '1.1.0',
            'APP_DATA_DIR'               => '/var/lib/data2md',
            'MAX_FILE_SIZE_MB'           => '200',
            'CONVERT_TIMEOUT_SECONDS'    => '900',
            'CONVERT_CONCURRENCY'        => '2',
            'CONVERT_QUEUE_WAIT_SECONDS' => '20',
            'CONVERT_NICE'               => '15',
            'PHP_MEMORY_LIMIT_MB'        => '512',
            'RATE_LIMIT_MAX'             => '20',
            'RATE_LIMIT_WINDOW_SECONDS'  => '60',
            'TRUSTED_PROXIES'            => '10.11.0.0/16',
            'CORS_ALLOWED_ORIGINS'       => '',
            'LOG_RETENTION_DAYS'         => '90',
            'AUDIT_LOG_RETENTION_DAYS'   => '30',
            'GC_PROBABILITY'             => '0.01',
            'CONTACT_EMAIL'              => 'contato@data2md.example',
        ]);

        self::assertSame(900 + 20 + 60, request_ceiling_seconds($config));
    }

    /**
     * docker/entrypoint.sh cannot call request_ceiling_seconds() — it runs
     * before PHP does — so it duplicates the formula in shell. This pins
     * that copy so a change to one side that forgets the other fails loudly
     * instead of silently drifting (see the comment on both sides).
     */
    public function testEntrypointShellMirrorsTheSameFormula(): void
    {
        $script = (string) file_get_contents(dirname(__DIR__, 2) . '/docker/entrypoint.sh');

        self::assertStringContainsString(
            'REQUEST_CEILING=$((CONVERT_TIMEOUT_SECONDS + CONVERT_QUEUE_WAIT_SECONDS + 60))',
            $script
        );
    }
}
