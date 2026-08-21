<?php

declare(strict_types=1);

namespace Data2Md\Tests\Unit\Config;

use Data2Md\Config\Config;
use Data2Md\Config\ConfigError;
use PHPUnit\Framework\TestCase;

final class ConfigTest extends TestCase
{
    /** @return array<string,string> */
    private static function valid(array $overrides = []): array
    {
        return $overrides + [
            'APP_ENV'                    => 'production',
            'APP_URL'                    => 'https://data2md.example.com',
            'APP_NAME'                   => 'data2md',
            'APP_VERSION'                => '1.1.0',
            'APP_DATA_DIR'               => '/var/lib/data2md',
            'MAX_FILE_SIZE_MB'           => '200',
            'CONVERT_TIMEOUT_SECONDS'    => '900',
            'CONVERT_CONCURRENCY'        => '2',
            'CONVERT_QUEUE_WAIT_SECONDS' => '120',
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
        ];
    }

    public function testValidEnvironmentProducesTypedAccess(): void
    {
        $config = Config::fromArray(self::valid());

        self::assertSame('data2md', $config->string('APP_NAME'));
        self::assertSame(200, $config->int('MAX_FILE_SIZE_MB'));
        self::assertSame(0.01, $config->float('GC_PROBABILITY'));
        self::assertSame(['10.11.0.0/16'], $config->list('TRUSTED_PROXIES'));
    }

    public function testMissingRequiredKeyFails(): void
    {
        $raw = self::valid();
        unset($raw['APP_URL']);

        $this->expectException(ConfigError::class);
        Config::fromArray($raw);
    }

    public function testAllProblemsAreReportedAtOnce(): void
    {
        $raw = self::valid([
            'MAX_FILE_SIZE_MB' => 'abc',
            'APP_ENV'          => 'staging',
            'GC_PROBABILITY'   => '2.5',
        ]);
        unset($raw['APP_URL']);

        try {
            Config::fromArray($raw);
            self::fail('expected ConfigError');
        } catch (ConfigError $e) {
            $problems = implode("\n", $e->getProblems());
            self::assertCount(4, $e->getProblems(), $problems);
            self::assertStringContainsString('APP_URL', $problems);
            self::assertStringContainsString('MAX_FILE_SIZE_MB', $problems);
            self::assertStringContainsString('APP_ENV', $problems);
            self::assertStringContainsString('GC_PROBABILITY', $problems);
        }
    }

    public function testEmptyIsAcceptedOnlyWhereAllowed(): void
    {
        $config = Config::fromArray(self::valid(['TRUSTED_PROXIES' => '']));
        self::assertSame([], $config->list('TRUSTED_PROXIES'));

        $this->expectException(ConfigError::class);
        Config::fromArray(self::valid(['APP_NAME' => '']));
    }

    public function testIntegerOutsideRangeFails(): void
    {
        $this->expectException(ConfigError::class);
        Config::fromArray(self::valid(['CONVERT_NICE' => '25']));
    }

    public function testUrlMustBeAbsoluteHttp(): void
    {
        $this->expectException(ConfigError::class);
        Config::fromArray(self::valid(['APP_URL' => 'ftp://example.com']));
    }

    public function testDataDirMustBeAbsolute(): void
    {
        $this->expectException(ConfigError::class);
        Config::fromArray(self::valid(['APP_DATA_DIR' => 'relative/path']));
    }

    public function testInvalidCidrFails(): void
    {
        $this->expectException(ConfigError::class);
        Config::fromArray(self::valid(['TRUSTED_PROXIES' => '10.11.0.0/99']));
    }

    public function testCidrListAcceptsMultipleAndPlainIps(): void
    {
        $config = Config::fromArray(self::valid([
            'TRUSTED_PROXIES' => '10.11.0.0/16, 203.0.113.200, ::1',
        ]));
        self::assertSame(['10.11.0.0/16', '203.0.113.200', '::1'], $config->list('TRUSTED_PROXIES'));
    }

    public function testUnknownKeyIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Config::fromArray(self::valid())->string('NOPE');
    }

    public function testOriginListAcceptsValidOriginsAndBuildsList(): void
    {
        $config = Config::fromArray(self::valid(['CORS_ALLOWED_ORIGINS' => 'https://app.example.com']));
        self::assertSame(['https://app.example.com'], $config->list('CORS_ALLOWED_ORIGINS'));

        $config = Config::fromArray(self::valid(['CORS_ALLOWED_ORIGINS' => 'http://localhost:8080']));
        self::assertSame(['http://localhost:8080'], $config->list('CORS_ALLOWED_ORIGINS'));

        $config = Config::fromArray(self::valid([
            'CORS_ALLOWED_ORIGINS' => 'https://a.example.com, https://b.example.com',
        ]));
        self::assertSame(
            ['https://a.example.com', 'https://b.example.com'],
            $config->list('CORS_ALLOWED_ORIGINS')
        );
    }

    /**
     * @dataProvider invalidOrigins
     */
    public function testOriginListRejectsInvalidEntries(string $origin): void
    {
        $this->expectException(ConfigError::class);
        Config::fromArray(self::valid(['CORS_ALLOWED_ORIGINS' => $origin]));
    }

    /** @return array<string,array{string}> */
    public static function invalidOrigins(): array
    {
        return [
            'missing scheme' => ['app.example.com'],
            'wrong scheme'   => ['ftp://app.example.com'],
            'with path'      => ['https://app.example.com/'],
        ];
    }

    public function testIntegerBelowRangeFails(): void
    {
        $this->expectException(ConfigError::class);
        Config::fromArray(self::valid(['CONVERT_TIMEOUT_SECONDS' => '10']));
    }

    public function testStringExceedingMaxLengthFails(): void
    {
        $this->expectException(ConfigError::class);
        Config::fromArray(self::valid(['APP_NAME' => str_repeat('a', 65)]));
    }

    public function testContactEmailMustBeValid(): void
    {
        $this->expectException(ConfigError::class);
        Config::fromArray(self::valid(['CONTACT_EMAIL' => 'not-an-email']));
    }

    public function testDevelopmentIsAValidAppEnv(): void
    {
        $config = Config::fromArray(self::valid(['APP_ENV' => 'development']));
        self::assertSame('development', $config->string('APP_ENV'));
    }

    public function testUrlTrailingSlashIsStripped(): void
    {
        $config = Config::fromArray(self::valid(['APP_URL' => 'https://data2md.example.com/']));
        self::assertSame('https://data2md.example.com', $config->string('APP_URL'));
    }

    public function testPathTrailingSlashIsStripped(): void
    {
        $config = Config::fromArray(self::valid(['APP_DATA_DIR' => '/var/lib/data2md/']));
        self::assertSame('/var/lib/data2md', $config->string('APP_DATA_DIR'));
    }
}
