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
        if (!$this->isEnabled() || $origin === null || !in_array($origin, $this->allowedOrigins, true)) {
            return [];
        }

        // Echo the specific origin rather than '*': the allowlist is the policy,
        // and Vary keeps caches from serving one origin's response to another.
        $headers = [
            'Access-Control-Allow-Origin' => $origin,
            'Vary'                        => 'Origin',
        ];

        if ($preflight) {
            $headers['Access-Control-Allow-Methods'] = 'POST, OPTIONS';
            $headers['Access-Control-Allow-Headers'] = 'Content-Type';
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

        return $this->headersFor($origin, preflight: true) === [] ? 403 : 204;
    }
}
