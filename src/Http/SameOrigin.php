<?php

declare(strict_types=1);

namespace Data2Md\Http;

/**
 * Whether a state-changing request came from this site's own pages.
 *
 * Used where a CSRF token would mean minting a session for every anonymous
 * visitor. It is a second line rather than the only one: a SameSite=Lax session
 * cookie is not sent on a cross-site POST at all, so a forged conversion is
 * simply an anonymous one.
 */
final class SameOrigin
{
    /** @param array<string,mixed> $server */
    public static function isAllowed(array $server, string $appUrl): bool
    {
        // Sec-Fetch-Site is a forbidden header name, so script cannot set it.
        $fetchSite = $server['HTTP_SEC_FETCH_SITE'] ?? null;

        if (is_string($fetchSite) && $fetchSite !== '') {
            return $fetchSite === 'same-origin';
        }

        $origin = $server['HTTP_ORIGIN'] ?? null;

        if (is_string($origin) && $origin !== '') {
            return self::hostOf($origin) === self::hostOf($appUrl)
                && self::hostOf($origin) !== null;
        }

        // Neither header: a curl call, an older browser, or a same-origin form
        // post. Allowed, because SameSite=Lax already keeps a forged one
        // anonymous and this endpoint serves anonymous callers anyway.
        return true;
    }

    private static function hostOf(string $url): ?string
    {
        $parts = parse_url($url);

        if (!is_array($parts) || !isset($parts['host'])) {
            return null;
        }

        $host = strtolower((string) $parts['host']);

        return isset($parts['port']) ? $host . ':' . $parts['port'] : $host;
    }
}
