<?php

declare(strict_types=1);

namespace Data2Md\Http;

/**
 * CORS via an explicit origin allowlist. Disabled when the allowlist is
 * empty, in which case a preflight request is answered with 405.
 */
final class Cors
{
    /** @param list<string> $allowedOrigins */
    public function __construct(private readonly array $allowedOrigins)
    {
    }

    public function isEnabled(): bool
    {
        return $this->allowedOrigins !== [];
    }

    /** @return array<string,string> */
    public function headersFor(?string $origin, bool $preflight = false): array
    {
        if (!$this->isEnabled()) {
            return [];
        }

        // The response varies by request origin whenever CORS is enabled —
        // one origin gets Allow-Origin, another gets nothing — so a shared
        // cache needs Vary regardless of whether this origin is on the list.
        if ($origin === null || !in_array($origin, $this->allowedOrigins, true)) {
            return ['Vary' => 'Origin'];
        }

        // Echo the specific origin rather than '*': the allowlist is the policy.
        $headers = [
            'Access-Control-Allow-Origin' => $origin,
            'Vary'                        => 'Origin',
        ];

        // The rate-limit headers are unreadable to cross-origin script without
        // Expose-Headers, which makes the published quota invisible to exactly
        // the callers who asked for CORS.
        $headers['Access-Control-Expose-Headers'] =
            'X-RateLimit-Limit, X-RateLimit-Remaining, X-RateLimit-Reset, Retry-After';

        if ($preflight) {
            $headers['Access-Control-Allow-Methods'] = 'GET, POST, OPTIONS';
            $headers['Access-Control-Allow-Headers'] = 'Content-Type, Authorization';
            $headers['Access-Control-Max-Age']       = '86400';
        }

        return $headers;
    }

    /** @return int HTTP status to send */
    public function handlePreflight(?string $origin): int
    {
        if (!$this->isEnabled()) {
            return 405;
        }

        return array_key_exists('Access-Control-Allow-Origin', $this->headersFor($origin, preflight: true))
            ? 204
            : 403;
    }
}
