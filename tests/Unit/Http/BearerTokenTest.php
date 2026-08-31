<?php

declare(strict_types=1);

namespace Data2Md\Tests\Unit\Http;

use Data2Md\Http\BearerToken;
use PHPUnit\Framework\TestCase;

final class BearerTokenTest extends TestCase
{
    /** @dataProvider headerValues */
    public function testParsesTheHeaderValue(string $value, ?string $expected): void
    {
        self::assertSame($expected, BearerToken::parse($value));
    }

    public static function headerValues(): array
    {
        return [
            'plain'            => ['Bearer d2md_abc', 'd2md_abc'],
            'lowercase scheme' => ['bearer d2md_abc', 'd2md_abc'],
            'mixed case'       => ['BEARER d2md_abc', 'd2md_abc'],
            'padded'           => ['  Bearer   d2md_abc  ', 'd2md_abc'],
            'tab separated'    => ["Bearer\td2md_abc", 'd2md_abc'],
            'no scheme'        => ['d2md_abc', null],
            'other scheme'     => ['Basic ZGVtbzpkZW1v', null],
            'scheme only'      => ['Bearer', null],
            'empty credential' => ['Bearer ', null],
            'two credentials'  => ['Bearer a b', null],
            'empty'            => ['', null],
        ];
    }

    public function testReadsTheServerVariableWhenTheSapiPopulatesIt(): void
    {
        self::assertSame(
            'd2md_from_server',
            BearerToken::fromRequest(['HTTP_AUTHORIZATION' => 'Bearer d2md_from_server'], fn (): array => [])
        );
    }

    /** mod_rewrite prefixes the variable when a rule has already fired. */
    public function testReadsTheRedirectedServerVariable(): void
    {
        self::assertSame(
            'd2md_redirected',
            BearerToken::fromRequest(
                ['REDIRECT_HTTP_AUTHORIZATION' => 'Bearer d2md_redirected'],
                fn (): array => []
            )
        );
    }

    /**
     * The case that actually happens in production: Apache withholds the header
     * from $_SERVER, and only the header reader has it.
     */
    public function testFallsBackToTheHeaderReader(): void
    {
        self::assertSame(
            'd2md_from_headers',
            BearerToken::fromRequest([], fn (): array => ['Authorization' => 'Bearer d2md_from_headers'])
        );
    }

    public function testHeaderNameMatchIsCaseInsensitive(): void
    {
        self::assertSame(
            'd2md_lowercase_name',
            BearerToken::fromRequest([], fn (): array => ['authorization' => 'Bearer d2md_lowercase_name'])
        );
    }

    public function testNoCredentialAnywhereIsNull(): void
    {
        self::assertNull(BearerToken::fromRequest(['HTTP_ACCEPT' => '*/*'], fn (): array => ['Accept' => '*/*']));
    }

    /** A non-bearer $_SERVER value must not stop the reader from being consulted. */
    public function testAnotherSchemeInServerDoesNotMaskTheHeader(): void
    {
        self::assertSame(
            'd2md_real',
            BearerToken::fromRequest(
                ['HTTP_AUTHORIZATION' => 'Basic ZGVtbzpkZW1v'],
                fn (): array => ['Authorization' => 'Bearer d2md_real']
            )
        );
    }
}
