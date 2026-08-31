<?php

declare(strict_types=1);

namespace Data2Md\Http;

/**
 * Reads the credential off an API request.
 *
 * Apache does not export Authorization into the CGI environment, so
 * $_SERVER['HTTP_AUTHORIZATION'] is absent under the apache2handler SAPI this
 * image runs — measured against the container, not assumed. It has to be read
 * through apache_request_headers(); relying on $_SERVER alone would answer 401
 * to every caller, including one holding a valid token. The $_SERVER keys stay
 * first in the chain for SAPIs that do populate them: php-fpm with
 * CGIPassAuth On, and PHP's built-in server.
 */
final class BearerToken
{
    /** @var list<string> */
    private const SERVER_KEYS = ['HTTP_AUTHORIZATION', 'REDIRECT_HTTP_AUTHORIZATION'];

    /**
     * @param array<string,mixed> $server $_SERVER
     * @param (\Closure(): array<string,mixed>)|null $headerReader defaults to apache_request_headers()
     */
    public static function fromRequest(array $server, ?\Closure $headerReader = null): ?string
    {
        foreach (self::SERVER_KEYS as $key) {
            $value = $server[$key] ?? null;

            if (is_string($value)) {
                $token = self::parse($value);

                if ($token !== null) {
                    return $token;
                }
            }
        }

        $headerReader ??= function_exists('apache_request_headers')
            ? apache_request_headers(...)
            : null;

        if ($headerReader === null) {
            return null;
        }

        foreach ($headerReader() as $name => $value) {
            if (is_string($name) && strcasecmp($name, 'Authorization') === 0 && is_string($value)) {
                return self::parse($value);
            }
        }

        return null;
    }

    /** The token out of an Authorization value, or null when it is not a bearer one. */
    public static function parse(string $headerValue): ?string
    {
        return preg_match('/^Bearer[ \t]+(\S+)$/i', trim($headerValue), $matches) === 1
            ? $matches[1]
            : null;
    }
}
