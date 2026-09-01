<?php

declare(strict_types=1);

namespace Data2Md\Tests\Unit\I18n;

use Data2Md\I18n\I18n;
use PHPUnit\Framework\TestCase;

/**
 * sanitizeBack() feeds a Location header, so anything it lets through is an
 * open redirect on this origin.
 */
final class I18nTest extends TestCase
{
    /**
     * @dataProvider sameOriginPaths
     */
    public function testSameOriginPathIsPreserved(string $back): void
    {
        self::assertSame($back, I18n::sanitizeBack($back));
    }

    public static function sameOriginPaths(): array
    {
        return [
            ['/'],
            ['/app'],
            ['/api'],
            ['/lang?to=pt'],
            ['/app?a=1&b=2'],
        ];
    }

    /**
     * @dataProvider offOriginTargets
     */
    public function testOffOriginTargetCollapsesToRoot(string $back, string $why): void
    {
        self::assertSame('/', I18n::sanitizeBack($back), $why);
    }

    public static function offOriginTargets(): array
    {
        return [
            'empty'                => ['', 'nothing to go back to'],
            'no leading slash'     => ['evil.com', 'not a relative path'],
            'absolute http'        => ['http://evil.com', 'different origin'],
            'absolute https'       => ['https://evil.com', 'different origin'],
            'protocol relative'    => ['//evil.com', 'inherits the scheme'],
            'backslash'            => ['/\\evil.com', 'backslash is / in a special scheme'],
            'backslash double'     => ['/\\\\evil.com', 'both collapse to slashes'],
            'backslash then slash' => ['/\\/evil.com', 'reads as //'],
            'space then slash'     => ['/ /evil.com', 'protocol-relative once stripped'],
            'tab then slash'       => ["/\t/evil.com", 'protocol-relative once stripped'],
            'newline then slash'   => ["/\n/evil.com", 'protocol-relative once stripped'],
            'null then slash'      => ["/\0/evil.com", 'protocol-relative once stripped'],
        ];
    }

    public function testHeaderInjectionCharactersAreRemoved(): void
    {
        $back = I18n::sanitizeBack("/app\r\nSet-Cookie: a=b");

        self::assertStringNotContainsString("\r", $back);
        self::assertStringNotContainsString("\n", $back);
    }

    /**
     * A key present in one language and missing in the other renders as the key
     * itself: I18n::t() falls back to the key string, with no second dictionary
     * behind it. The page then shows "api.auth.body" to the visitor.
     */
    public function testEveryLanguageDefinesTheSameKeys(): void
    {
        $en = self::dictionary('en');
        $pt = self::dictionary('pt');

        self::assertSame([], array_keys(array_diff_key($en, $pt)), 'keys missing from pt.php');
        self::assertSame([], array_keys(array_diff_key($pt, $en)), 'keys missing from en.php');
    }

    /**
     * t() passes its arguments to vsprintf, which throws when a pattern wants
     * more of them than the call site has. A translator adding one %s turns the
     * page it is on into a 500.
     */
    public function testPlaceholdersAgreeAcrossLanguages(): void
    {
        $en = self::dictionary('en');
        $pt = self::dictionary('pt');

        foreach ($en as $key => $englishText) {
            self::assertSame(
                self::placeholders($englishText),
                self::placeholders($pt[$key]),
                "placeholders differ for '$key'"
            );
        }
    }

    /** @return array<string,mixed> */
    private static function dictionary(string $locale): array
    {
        /** @var array<string,string> $loaded */
        $loaded = require dirname(__DIR__, 3) . '/src/I18n/translations/' . $locale . '.php';

        return $loaded;
    }

    /**
     * How many arguments a pattern consumes, and which positions it names.
     * Word order legitimately differs between languages; the count of
     * placeholders and the highest position referenced do not.
     *
     * @return array{count:int,highestPosition:int}
     */
    private static function placeholders(string $pattern): array
    {
        // %% is a literal percent sign and takes no argument.
        $pattern = str_replace('%%', '', $pattern);

        preg_match_all('/%(\d+)\$[a-zA-Z]/', $pattern, $positional);

        $positions = array_map('intval', $positional[1]);
        $remaining = preg_replace('/%\d+\$[a-zA-Z]/', '', $pattern);

        preg_match_all('/%[-+ 0#\']*[0-9]*(?:\.[0-9]+)?[a-zA-Z]/', (string) $remaining, $plain);

        return [
            'count'           => count($plain[0]),
            'highestPosition' => $positions === [] ? 0 : max($positions),
        ];
    }
}
