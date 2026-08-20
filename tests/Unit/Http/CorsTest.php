<?php

declare(strict_types=1);

namespace Data2Md\Tests\Unit\Http;

use Data2Md\Http\Cors;
use PHPUnit\Framework\TestCase;

final class CorsTest extends TestCase
{
    public function testDisabledWhenNoOriginsAreConfigured(): void
    {
        $cors = new Cors([]);

        self::assertFalse($cors->isEnabled());
        self::assertSame([], $cors->headersFor('https://example.com'));
    }

    public function testDisabledPreflightIsMethodNotAllowed(): void
    {
        self::assertSame(405, (new Cors([]))->handlePreflight('https://example.com'));
    }

    public function testAllowedOriginIsEchoedBack(): void
    {
        $headers = (new Cors(['https://app.example.com']))->headersFor('https://app.example.com');

        self::assertSame('https://app.example.com', $headers['Access-Control-Allow-Origin']);
        self::assertSame('Origin', $headers['Vary']);
    }

    public function testDisallowedOriginGetsVaryButNoAllowOrigin(): void
    {
        $headers = (new Cors(['https://app.example.com']))->headersFor('https://evil.example.com');

        self::assertArrayNotHasKey('Access-Control-Allow-Origin', $headers);
        self::assertSame('Origin', $headers['Vary']);
    }

    public function testNullOriginGetsVaryButNoAllowOrigin(): void
    {
        $headers = (new Cors(['https://app.example.com']))->headersFor(null);

        self::assertArrayNotHasKey('Access-Control-Allow-Origin', $headers);
        self::assertSame('Origin', $headers['Vary']);
    }

    public function testWildcardIsNeverEchoed(): void
    {
        $headers = (new Cors(['https://a.example.com', 'https://b.example.com']))
            ->headersFor('https://b.example.com');

        self::assertSame('https://b.example.com', $headers['Access-Control-Allow-Origin']);
    }

    public function testPreflightForAnAllowedOriginSucceeds(): void
    {
        self::assertSame(204, (new Cors(['https://app.example.com']))->handlePreflight('https://app.example.com'));
    }

    public function testPreflightForADisallowedOriginIsForbidden(): void
    {
        self::assertSame(403, (new Cors(['https://app.example.com']))->handlePreflight('https://evil.example.com'));
    }

    public function testPreflightAdvertisesPostAndTheContentType(): void
    {
        $headers = (new Cors(['https://app.example.com']))->headersFor('https://app.example.com', preflight: true);

        self::assertStringContainsString('POST', $headers['Access-Control-Allow-Methods']);
        self::assertArrayHasKey('Access-Control-Max-Age', $headers);
    }
}
