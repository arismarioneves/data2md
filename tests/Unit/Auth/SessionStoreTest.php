<?php

declare(strict_types=1);

namespace Data2Md\Tests\Unit\Auth;

use Data2Md\Auth\SessionStore;
use Data2Md\Auth\UserStore;
use Data2Md\Storage\Database;
use PHPUnit\Framework\TestCase;

final class SessionStoreTest extends TestCase
{
    private const LIFETIME_DAYS = 30;
    private const IDLE_DAYS     = 14;

    private string $dbPath;
    private Database $db;
    private SessionStore $sessions;
    private int $userId;

    protected function setUp(): void
    {
        $this->dbPath = sys_get_temp_dir() . '/d2md-sess-' . bin2hex(random_bytes(6)) . '.sqlite';

        $this->db = new Database($this->dbPath);
        $this->db->migrate();

        $this->sessions = new SessionStore($this->db, self::LIFETIME_DAYS, self::IDLE_DAYS);
        $this->userId   = (new UserStore($this->db))->ensure('alice@example.com');
    }

    protected function tearDown(): void
    {
        foreach ([$this->dbPath, $this->dbPath . '-wal', $this->dbPath . '-shm'] as $file) {
            if (file_exists($file)) {
                unlink($file);
            }
        }
    }

    public function testCreatedSessionResolvesToItsAccount(): void
    {
        $raw = $this->sessions->create($this->userId);

        $found = $this->sessions->find($raw);

        self::assertNotNull($found);
        self::assertSame($this->userId, (int) $found['id']);
        self::assertSame('alice@example.com', $found['email']);
        self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $found['csrf_token']);
    }

    public function testCookieValueIsNotStored(): void
    {
        $raw = $this->sessions->create($this->userId);

        $stored = $this->db->pdo()->query('SELECT token_hash FROM sessions')->fetchColumn();

        self::assertNotSame($raw, $stored);
        self::assertSame(hash('sha256', $raw), $stored);
    }

    public function testEachSessionGetsItsOwnCsrfToken(): void
    {
        $a = $this->sessions->find($this->sessions->create($this->userId));
        $b = $this->sessions->find($this->sessions->create($this->userId));

        self::assertNotSame($a['csrf_token'], $b['csrf_token']);
    }

    public function testUnknownTokenResolvesToNothing(): void
    {
        self::assertNull($this->sessions->find(bin2hex(random_bytes(32))));
    }

    /** Staying active moves the idle cut, but never the absolute lifetime. */
    public function testAbsoluteLifetimeEndsEvenAContinuouslyUsedSession(): void
    {
        $createdAt = 1_700_000_000;
        $raw       = $this->sessions->create($this->userId, $createdAt);
        $lifetime  = self::LIFETIME_DAYS * 86400;

        for ($day = 5; $day < self::LIFETIME_DAYS; $day += 5) {
            self::assertNotNull(
                $this->sessions->find($raw, $createdAt + ($day * 86400)),
                "still valid on day $day"
            );
        }

        self::assertNull($this->sessions->find($raw, $createdAt + $lifetime + 10));
    }

    public function testIdleSessionIsRejectedBeforeItsLifetimeEnds(): void
    {
        $createdAt = 1_700_000_000;
        $raw       = $this->sessions->create($this->userId, $createdAt);

        $idle = self::IDLE_DAYS * 86400;

        self::assertNull(
            $this->sessions->find($raw, $createdAt + $idle + 10),
            'idle expiry must bite before the 30-day lifetime'
        );
    }

    /** A read must not take the write lock on every request. */
    public function testLastSeenIsNotWrittenOnEveryLookup(): void
    {
        $createdAt = 1_700_000_000;
        $raw       = $this->sessions->create($this->userId, $createdAt);

        $this->sessions->find($raw, $createdAt + 60);

        self::assertSame($createdAt, $this->lastSeen(), 'a recent session is left alone');

        $this->sessions->find($raw, $createdAt + 7200);

        self::assertSame($createdAt + 7200, $this->lastSeen(), 'an hour later it is refreshed');
    }

    public function testActivityKeepsAnIdleSessionAlive(): void
    {
        $createdAt = 1_700_000_000;
        $raw       = $this->sessions->create($this->userId, $createdAt);
        $idle      = self::IDLE_DAYS * 86400;

        // Seen again a week in, which moves the idle cut with it.
        self::assertNotNull($this->sessions->find($raw, $createdAt + (7 * 86400)));
        self::assertNotNull($this->sessions->find($raw, $createdAt + $idle + 10));
    }

    public function testDestroyRemovesOnlyThatSession(): void
    {
        $first  = $this->sessions->create($this->userId);
        $second = $this->sessions->create($this->userId);

        $this->sessions->destroy($first);

        self::assertNull($this->sessions->find($first));
        self::assertNotNull($this->sessions->find($second));
    }

    public function testDestroyAllForUserSignsEveryDeviceOut(): void
    {
        $first  = $this->sessions->create($this->userId);
        $second = $this->sessions->create($this->userId);

        self::assertSame(2, $this->sessions->destroyAllFor($this->userId));
        self::assertNull($this->sessions->find($first));
        self::assertNull($this->sessions->find($second));
    }

    /** foreign_keys is on, so a deleted account cannot leave a live session. */
    public function testDeletingTheAccountCascadesToItsSessions(): void
    {
        $raw = $this->sessions->create($this->userId);

        (new UserStore($this->db))->delete($this->userId);

        self::assertSame(0, $this->sessions->count());
        self::assertNull($this->sessions->find($raw));
    }

    public function testPurgeRemovesExpiredAndIdleSessions(): void
    {
        $createdAt = 1_700_000_000;
        $this->sessions->create($this->userId, $createdAt);
        $this->sessions->create($this->userId, $createdAt);

        self::assertSame(0, $this->sessions->purgeExpired($createdAt + 60));
        self::assertSame(2, $this->sessions->purgeExpired($createdAt + (self::IDLE_DAYS * 86400) + 10));
        self::assertSame(0, $this->sessions->count());
    }

    private function lastSeen(): int
    {
        return (int) $this->db->pdo()->query('SELECT last_seen_at FROM sessions')->fetchColumn();
    }
}
