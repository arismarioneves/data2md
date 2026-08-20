<?php

declare(strict_types=1);

namespace Data2Md\Tests\Unit\Http;

use Data2Md\Http\ClientIp;
use PHPUnit\Framework\TestCase;

final class ClientIpTest extends TestCase
{
    private const TRUSTED = ['10.11.0.0/16', '203.0.113.200', '::1'];

    private function resolver(array $trusted = self::TRUSTED): ClientIp
    {
        return new ClientIp($trusted);
    }

    public function testHeaderFromUntrustedOriginIsIgnored(): void
    {
        $ip = $this->resolver()->resolve([
            'REMOTE_ADDR'          => '203.0.113.9',
            'HTTP_X_FORWARDED_FOR' => '1.2.3.4',
        ]);

        self::assertSame('203.0.113.9', $ip, 'a spoofed header must not pick the bucket');
    }

    public function testHeaderFromTrustedProxyIsHonoured(): void
    {
        $ip = $this->resolver()->resolve([
            'REMOTE_ADDR'          => '10.11.0.5',
            'HTTP_X_FORWARDED_FOR' => '203.0.113.9',
        ]);

        self::assertSame('203.0.113.9', $ip);
    }

    public function testRightmostUntrustedEntryWinsInAChain(): void
    {
        $ip = $this->resolver()->resolve([
            'REMOTE_ADDR'          => '10.11.0.5',
            'HTTP_X_FORWARDED_FOR' => '9.9.9.9, 203.0.113.9, 10.11.5.5',
        ]);

        self::assertSame('203.0.113.9', $ip, 'entries a client can forge sit to the left');
    }

    public function testFallsBackToRemoteAddrWhenChainIsAllTrusted(): void
    {
        $ip = $this->resolver()->resolve([
            'REMOTE_ADDR'          => '10.11.0.5',
            'HTTP_X_FORWARDED_FOR' => '10.11.0.9, 10.11.0.3',
        ]);

        self::assertSame('10.11.0.5', $ip);
    }

    public function testMalformedEntriesAreSkipped(): void
    {
        $ip = $this->resolver()->resolve([
            'REMOTE_ADDR'          => '10.11.0.5',
            'HTTP_X_FORWARDED_FOR' => '203.0.113.9, not-an-ip, ',
        ]);

        self::assertSame('203.0.113.9', $ip);
    }

    public function testEmptyAllowlistDisablesHeaderEntirely(): void
    {
        $ip = (new ClientIp([]))->resolve([
            'REMOTE_ADDR'          => '10.11.0.5',
            'HTTP_X_FORWARDED_FOR' => '203.0.113.9',
        ]);

        self::assertSame('10.11.0.5', $ip);
    }

    public function testMissingRemoteAddrYieldsSentinel(): void
    {
        self::assertSame('0.0.0.0', $this->resolver()->resolve([]));
    }

    public function testIpv6ProxyAndChain(): void
    {
        // Deliberately narrower than 2001:db8::/32: that wider block also
        // covers the "external client" address below (both share the first
        // 32 bits, 2001:0db8), which would make it indistinguishable from a
        // trusted hop. /48 keeps the proxy hops (2001:db8:1::x) inside the
        // trust boundary while the client (2001:db8:ffff::9) falls outside it.
        $ip = $this->resolver(['2001:db8:1::/48'])->resolve([
            'REMOTE_ADDR'          => '2001:db8:1::5',
            'HTTP_X_FORWARDED_FOR' => '2001:db8:ffff::9, 2001:db8:1::2',
        ]);

        self::assertSame('2001:db8:ffff::9', $ip);
    }

    /** @dataProvider membership */
    public function testCidrMembership(string $ip, string $cidr, bool $expected): void
    {
        self::assertSame($expected, (new ClientIp([$cidr]))->isTrusted($ip));
    }

    public static function membership(): array
    {
        return [
            ['172.16.0.1',    '172.16.0.0/12', true],
            ['172.31.255.254','172.16.0.0/12', true],
            ['172.32.0.1',    '172.16.0.0/12', false],
            ['192.168.1.1',   '172.16.0.0/12', false],
            ['10.0.0.1',      '10.0.0.1',      true],
            ['10.0.0.2',      '10.0.0.1',      false],
            ['0.0.0.0',       '0.0.0.0/0',     true],
            ['203.0.113.1',   '0.0.0.0/0',     true],
            ['2001:db8::1',   '2001:db8::/32', true],
            ['2001:db9::1',   '2001:db8::/32', false],
            ['2001:db8::1',   '172.16.0.0/12', false],
        ];
    }
}
