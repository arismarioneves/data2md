<?php

declare(strict_types=1);

namespace Data2Md\Tests\Unit\Http;

use Data2Md\Http\SameOrigin;
use PHPUnit\Framework\TestCase;

final class SameOriginTest extends TestCase
{
    private const APP_URL = 'https://data2md.example.com';

    /**
     * @dataProvider requests
     * @param array<string,mixed> $server
     */
    public function testVerdict(array $server, bool $expected, string $why): void
    {
        self::assertSame($expected, SameOrigin::isAllowed($server, self::APP_URL), $why);
    }

    public static function requests(): array
    {
        return [
            // Sec-Fetch-Site is a forbidden header name, so it decides alone.
            'fetch site same-origin' => [['HTTP_SEC_FETCH_SITE' => 'same-origin'], true, 'our own page'],
            'fetch site cross-site'  => [['HTTP_SEC_FETCH_SITE' => 'cross-site'], false, 'another site'],
            'fetch site same-site'   => [['HTTP_SEC_FETCH_SITE' => 'same-site'], false, 'a sibling subdomain is not us'],
            'fetch site none'        => [['HTTP_SEC_FETCH_SITE' => 'none'], false, 'typed into the address bar'],

            // It also wins over a forgeable Origin.
            'fetch site overrides a matching origin' => [
                ['HTTP_SEC_FETCH_SITE' => 'cross-site', 'HTTP_ORIGIN' => self::APP_URL],
                false,
                'the unforgeable header decides',
            ],

            'origin matches'   => [['HTTP_ORIGIN' => self::APP_URL], true, 'same host'],
            'origin differs'   => [['HTTP_ORIGIN' => 'https://evil.example.com'], false, 'different host'],
            'origin null'      => [['HTTP_ORIGIN' => 'null'], false, 'opaque origin has no host'],
            'origin garbage'   => [['HTTP_ORIGIN' => 'not a url'], false, 'unparseable'],
            'origin port differs' => [['HTTP_ORIGIN' => 'https://data2md.example.com:8443'], false, 'port is part of the origin'],
            'origin case'      => [['HTTP_ORIGIN' => 'https://DATA2MD.EXAMPLE.COM'], true, 'host comparison is case-insensitive'],

            // SameSite=Lax already keeps a forged request anonymous, and this
            // endpoint serves anonymous callers anyway.
            'neither header'   => [[], true, 'curl, an older browser, or a same-origin form post'],
            'empty headers'    => [['HTTP_SEC_FETCH_SITE' => '', 'HTTP_ORIGIN' => ''], true, 'absent in effect'],
        ];
    }

    public function testAnUnparseableAppUrlBlocksACrossOriginPost(): void
    {
        self::assertFalse(SameOrigin::isAllowed(['HTTP_ORIGIN' => 'https://evil.example.com'], 'not-a-url'));
    }
}
