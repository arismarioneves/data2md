<?php

declare(strict_types=1);

namespace Data2Md\Http;

/**
 * Resolves the client address behind a reverse proxy.
 *
 * X-Forwarded-For is appended to by each hop, so entries to the LEFT can be
 * forged freely by the client — only the rightmost entries, written by proxies
 * we control, are trustworthy. Walks right to left and returns the first
 * address that is not one of the trusted proxies.
 *
 * If REMOTE_ADDR itself is not a trusted proxy, the header is ignored
 * completely.
 */
final class ClientIp
{
    private const SENTINEL = '0.0.0.0';

    /** @param list<string> $trustedProxies CIDRs or bare addresses */
    public function __construct(private readonly array $trustedProxies)
    {
    }

    /** @param array<string,mixed> $server */
    public function resolve(array $server): string
    {
        $remote = trim((string) ($server['REMOTE_ADDR'] ?? ''));

        if ($remote === '' || filter_var($remote, FILTER_VALIDATE_IP) === false) {
            return self::SENTINEL;
        }

        if ($this->trustedProxies === [] || !$this->isTrusted($remote)) {
            return $remote;
        }

        $chain = explode(',', (string) ($server['HTTP_X_FORWARDED_FOR'] ?? ''));

        for ($i = count($chain) - 1; $i >= 0; $i--) {
            $candidate = trim($chain[$i]);

            if ($candidate === '' || filter_var($candidate, FILTER_VALIDATE_IP) === false) {
                continue;
            }
            if (!$this->isTrusted($candidate)) {
                return $candidate;
            }
        }

        return $remote;
    }

    public function isTrusted(string $ip): bool
    {
        foreach ($this->trustedProxies as $cidr) {
            if (self::inCidr($ip, $cidr)) {
                return true;
            }
        }
        return false;
    }

    public static function inCidr(string $ip, string $cidr): bool
    {
        $packedIp = @inet_pton($ip);
        if ($packedIp === false) {
            return false;
        }

        if (!str_contains($cidr, '/')) {
            $packedNet = @inet_pton($cidr);
            return $packedNet !== false && $packedNet === $packedIp;
        }

        [$network, $bits] = explode('/', $cidr, 2);

        $packedNet = @inet_pton($network);
        if ($packedNet === false || strlen($packedNet) !== strlen($packedIp)) {
            return false; // mixing IPv4 and IPv6 never matches
        }

        // Fail closed on a malformed prefix (missing, non-numeric, negative,
        // or beyond the address family's width) — never treat it as trusted.
        if (!preg_match('/^\d+$/', $bits)) {
            return false;
        }

        $maxBits = strlen($packedNet) * 8; // 32 for IPv4, 128 for IPv6
        $prefix  = (int) $bits;
        if ($prefix > $maxBits) {
            return false;
        }

        if ($prefix === 0) {
            return true;
        }

        $wholeBytes = intdiv($prefix, 8);
        $spareBits  = $prefix % 8;

        if ($wholeBytes > 0 && substr($packedIp, 0, $wholeBytes) !== substr($packedNet, 0, $wholeBytes)) {
            return false;
        }

        if ($spareBits === 0) {
            return true;
        }

        $mask = ~((1 << (8 - $spareBits)) - 1) & 0xFF;

        return (ord($packedIp[$wholeBytes]) & $mask) === (ord($packedNet[$wholeBytes]) & $mask);
    }
}
