<?php

declare(strict_types=1);

namespace Data2Md\Tests\Unit\Auth;

use Data2Md\Auth\Session;
use Data2Md\Auth\SessionStore;
use Data2Md\Auth\UserStore;
use Data2Md\Storage\Database;
use PHPUnit\Framework\TestCase;

final class SessionTest extends TestCase
{
    private string $dbPath;
    private Database $db;
    private SessionStore $store;
    private int $userId;

    /** @var list<array{name:string,value:string,options:array<string,mixed>}> */
    private array $cookies = [];

    protected function setUp(): void
    {
        $this->dbPath = sys_get_temp_dir() . '/d2md-session-' . bin2hex(random_bytes(6)) . '.sqlite';

        $this->db = new Database($this->dbPath);
        $this->db->migrate();

        $this->store  = new SessionStore($this->db, 30, 14);
        $this->userId = (new UserStore($this->db))->ensure('alice@example.com');
    }

    protected function tearDown(): void
    {
        foreach ([$this->dbPath, $this->dbPath . '-wal', $this->dbPath . '-shm'] as $file) {
            if (file_exists($file)) {
                unlink($file);
            }
        }
    }

    private function session(bool $secure = true): Session
    {
        return new Session(
            store:        $this->store,
            secureCookie: $secure,
            lifetimeDays: 30,
            cookieWriter: function (string $name, string $value, array $options): void {
                $this->cookies[] = ['name' => $name, 'value' => $value, 'options' => $options];
            },
        );
    }

    /** @return array{name:string,value:string,options:array<string,mixed>} */
    private function lastCookie(): array
    {
        self::assertNotSame([], $this->cookies, 'no cookie was written');

        return $this->cookies[count($this->cookies) - 1];
    }

    public function testAnonymousRequestCreatesNothing(): void
    {
        $session = $this->session();
        $session->load([]);

        self::assertFalse($session->isAuthenticated());
        self::assertNull($session->userId());
        self::assertNull($session->csrfToken());
        self::assertSame(0, $this->store->count(), 'a visitor must not mint a session');
        self::assertSame([], $this->cookies, 'and must not be sent a cookie');
    }

    public function testStartSignsInAndSetsTheCookie(): void
    {
        $session = $this->session();
        $session->start($this->userId);

        self::assertTrue($session->isAuthenticated());
        self::assertSame($this->userId, $session->userId());
        self::assertSame('alice@example.com', $session->email());

        $cookie = $this->lastCookie();

        self::assertSame(Session::COOKIE, $cookie['name']);
        self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $cookie['value']);
    }

    /**
     * @dataProvider cookieHardening
     */
    public function testCookieIsHardened(string $option, mixed $expected): void
    {
        $session = $this->session();
        $session->start($this->userId);

        self::assertSame($expected, $this->lastCookie()['options'][$option]);
    }

    public static function cookieHardening(): array
    {
        return [
            'not readable by script' => ['httponly', true],
            'https only'             => ['secure', true],
            // Strict would withhold the cookie on the navigation back from a
            // payment page and on the sign-in link itself.
            'lax not strict'         => ['samesite', 'Lax'],
            'whole origin'           => ['path', '/'],
        ];
    }

    /** Traefik terminates TLS, so the flag follows APP_URL rather than the request. */
    public function testSecureFlagFollowsTheConfiguredScheme(): void
    {
        $session = $this->session(secure: false);
        $session->start($this->userId);

        self::assertFalse($this->lastCookie()['options']['secure']);
    }

    public function testLoadResolvesAnIssuedCookie(): void
    {
        $issuer = $this->session();
        $issuer->start($this->userId);
        $raw = $this->lastCookie()['value'];

        $session = $this->session();
        $session->load([Session::COOKIE => $raw]);

        self::assertTrue($session->isAuthenticated());
        self::assertSame($this->userId, $session->userId());
    }

    public function testUnknownCookieIsClearedNotHonoured(): void
    {
        $session = $this->session();
        $session->load([Session::COOKIE => bin2hex(random_bytes(32))]);

        self::assertFalse($session->isAuthenticated());
        self::assertSame('', $this->lastCookie()['value'], 'the stale cookie is expired away');
        self::assertLessThan(time(), $this->lastCookie()['options']['expires']);
    }

    /** Signing in again must not let a fixed token survive. */
    public function testStartReplacesAnExistingSession(): void
    {
        $session = $this->session();
        $session->start($this->userId);
        $first = $this->lastCookie()['value'];

        $session->start($this->userId);
        $second = $this->lastCookie()['value'];

        self::assertNotSame($first, $second);
        self::assertNull($this->store->find($first), 'the previous token stops working');
        self::assertNotNull($this->store->find($second));
        self::assertSame(1, $this->store->count());
    }

    public function testDestroyRevokesServerSideAndClearsTheCookie(): void
    {
        $session = $this->session();
        $session->start($this->userId);
        $raw = $this->lastCookie()['value'];

        $session->destroy();

        self::assertFalse($session->isAuthenticated());
        self::assertNull($this->store->find($raw), 'revoked in the database, not just in the browser');
        self::assertSame('', $this->lastCookie()['value']);
    }

    /**
     * @dataProvider csrfCandidates
     */
    public function testCsrfComparison(bool $useRealToken, mixed $presented, bool $expected): void
    {
        $session = $this->session();
        $session->start($this->userId);

        $value = $useRealToken ? $session->csrfToken() : $presented;

        self::assertSame($expected, $session->checkCsrf($value));
    }

    public static function csrfCandidates(): array
    {
        return [
            'the session token' => [true, null, true],
            'wrong token'       => [false, 'not-the-token', false],
            'empty'             => [false, '', false],
            'null'              => [false, null, false],
            'array'             => [false, ['x'], false],
        ];
    }

    public function testAnonymousSessionRejectsEveryCsrfValue(): void
    {
        $session = $this->session();
        $session->load([]);

        self::assertFalse($session->checkCsrf('anything'));
        self::assertFalse($session->checkCsrf(null));
    }
}
