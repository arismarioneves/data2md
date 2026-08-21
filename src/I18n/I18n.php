<?php

declare(strict_types=1);

namespace Data2Md\I18n;

/**
 * Tiny i18n helper.
 *
 * Resolution order (first hit wins):
 *   1. Cookie `d2md_lang` (set by /lang endpoint when user picks manually)
 *   2. Accept-Language header (auto-detect)
 *   3. Fallback to FALLBACK
 */
final class I18n
{
    public const COOKIE   = 'd2md_lang';
    public const FALLBACK = 'en';

    /** @var list<string> */
    public const SUPPORTED = ['en', 'pt'];

    /** @var array<string,string> */
    private static array $dict = [];

    private static string $lang = self::FALLBACK;

    public static function init(): void
    {
        self::$lang = self::detect();
        $file = __DIR__ . '/translations/' . self::$lang . '.php';
        if (is_file($file)) {
            /** @var array<string,string> $loaded */
            $loaded = require $file;
            self::$dict = $loaded;
        }
    }

    public static function detect(): string
    {
        // 1. Cookie
        $cookie = $_COOKIE[self::COOKIE] ?? null;
        if (is_string($cookie) && in_array($cookie, self::SUPPORTED, true)) {
            return $cookie;
        }

        // 2. Accept-Language header
        $header = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '';
        if (is_string($header) && $header !== '') {
            // Parse the first matching code; pt or pt-* → pt, en or en-* → en
            $parts = explode(',', strtolower($header));
            foreach ($parts as $part) {
                $code = trim(explode(';', $part)[0]);
                if ($code === 'pt' || str_starts_with($code, 'pt-')) {
                    return 'pt';
                }
                if ($code === 'en' || str_starts_with($code, 'en-')) {
                    return 'en';
                }
            }
        }

        return self::FALLBACK;
    }

    public static function current(): string
    {
        return self::$lang;
    }

    /**
     * @param scalar ...$args
     */
    public static function t(string $key, mixed ...$args): string
    {
        $val = self::$dict[$key] ?? $key;
        if ($args === []) {
            return $val;
        }
        return vsprintf($val, $args);
    }

    /**
     * Return only the keys consumed by the JS layer, for window.D2MD.i18n.
     *
     * @return array<string,string>
     */
    public static function jsBundle(): array
    {
        $keys = [
            'app.loading.uploading',
            'app.loading.extracting',
            'app.loading.structuring',
            'app.loading.building',
            'app.loading.finalising',
            'app.loading.done',
            'app.error.network',
            'app.error.timeout',
            'app.error.failed',
            'app.error.preview_truncated',
            'app.result.copying',
            'app.result.copied',
            'app.result.preview.chars',
            'app.tip.0',
            'app.tip.1',
            'app.tip.2',
            'app.tip.3',
            'app.tip.4',
            'app.error.busy',
            'app.status.queued',
            'app.status.retrying',
        ];
        $out = [];
        foreach ($keys as $k) {
            $out[$k] = self::$dict[$k] ?? $k;
        }
        return $out;
    }

    /**
     * Build the URL of the /lang endpoint with safe back-target.
     */
    public static function switchUrl(string $lang, string $back): string
    {
        $back = self::sanitizeBack($back);
        return '/lang?to=' . rawurlencode($lang) . '&back=' . rawurlencode($back);
    }

    /**
     * Only allow same-origin relative paths starting with `/`, no `//`, no scheme.
     */
    public static function sanitizeBack(string $back): string
    {
        if ($back === '' || $back[0] !== '/' || str_starts_with($back, '//')) {
            return '/';
        }
        // Strip control chars and spaces
        $back = preg_replace('/[\x00-\x1F\x7F\s]/u', '', $back) ?? '/';
        return $back === '' ? '/' : $back;
    }
}
