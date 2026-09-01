<?php

declare(strict_types=1);

namespace Data2Md\Tests\Unit\Auth;

use Data2Md\Auth\UserStore;
use Data2Md\Billing\Entitlement;
use Data2Md\Storage\Database;
use PHPUnit\Framework\TestCase;

final class UserStoreTest extends TestCase
{
    private string $dbPath;
    private UserStore $users;

    protected function setUp(): void
    {
        $this->dbPath = sys_get_temp_dir() . '/d2md-users-' . bin2hex(random_bytes(6)) . '.sqlite';

        $db = new Database($this->dbPath);
        $db->migrate();

        $this->users = new UserStore($db);
    }

    protected function tearDown(): void
    {
        foreach ([$this->dbPath, $this->dbPath . '-wal', $this->dbPath . '-shm'] as $file) {
            if (file_exists($file)) {
                unlink($file);
            }
        }
    }

    /**
     * @dataProvider emailForms
     */
    public function testEmailIsNormalised(string $input, string $expected): void
    {
        self::assertSame($expected, UserStore::normalizeEmail($input));
    }

    public static function emailForms(): array
    {
        return [
            'already canonical' => ['alice@example.com', 'alice@example.com'],
            'uppercase'         => ['Alice@Example.COM', 'alice@example.com'],
            'padded'            => ["  alice@example.com \n", 'alice@example.com'],
            // Different inboxes as far as this app is concerned.
            'plus tag kept'     => ['alice+data2md@example.com', 'alice+data2md@example.com'],
            'dots kept'         => ['a.l.i.c.e@example.com', 'a.l.i.c.e@example.com'],
        ];
    }

    public function testEnsureCreatesThenReturnsTheSameAccount(): void
    {
        $first = $this->users->ensure('alice@example.com');

        self::assertSame(1, $this->users->count());
        self::assertSame($first, $this->users->ensure('alice@example.com'));
        self::assertSame(1, $this->users->count());
    }

    public function testCaseDifferingAddressesAreOneAccount(): void
    {
        $id = $this->users->ensure('Alice@Example.com');

        self::assertSame($id, $this->users->ensure('alice@example.com'));
        self::assertSame($id, $this->users->ensure('ALICE@EXAMPLE.COM'));
        self::assertSame(1, $this->users->count());
    }

    public function testFindByEmailIsCaseInsensitive(): void
    {
        $id = $this->users->ensure('alice@example.com');

        $found = $this->users->findByEmail('ALICE@example.COM');

        self::assertNotNull($found);
        self::assertSame($id, (int) $found['id']);
    }

    public function testNewAccountStartsWithNoSubscription(): void
    {
        $this->users->ensure('alice@example.com');
        $user = $this->users->findByEmail('alice@example.com');

        self::assertNull($user['last_login_at']);
        self::assertNull($user['stripe_customer_id']);
        self::assertNull($user['subscription_status']);
        self::assertNull($user['premium_until']);
        self::assertSame(0, (int) $user['cancel_at_period_end']);
    }

    public function testTouchLastLoginRecordsTheTime(): void
    {
        $id = $this->users->ensure('alice@example.com');
        $this->users->touchLastLogin($id, 1_700_000_000);

        self::assertSame(1_700_000_000, (int) $this->users->findById($id)['last_login_at']);
    }

    public function testDeleteRemovesTheAccount(): void
    {
        $id = $this->users->ensure('alice@example.com');

        self::assertTrue($this->users->delete($id));
        self::assertNull($this->users->findById($id));
        self::assertFalse($this->users->delete($id));
    }

    public function testUnknownAddressIsNotFound(): void
    {
        self::assertNull($this->users->findByEmail('nobody@example.com'));
        self::assertNull($this->users->findById(999));
    }
    public function testPremiumGrantIsStoredAndCleared(): void
    {
        $id = $this->users->ensure('courtesy@example.com');

        self::assertNull($this->users->findById($id)['premium_until']);

        $this->users->setPremiumUntil($id, 2_000_000_000);
        self::assertSame(2_000_000_000, (int) $this->users->findById($id)['premium_until']);

        $this->users->setPremiumUntil($id, null);
        self::assertNull($this->users->findById($id)['premium_until']);
    }

    /** The grant is what Entitlement reads first, so it has to reach that check. */
    public function testPremiumGrantMakesTheAccountPremium(): void
    {
        $id = $this->users->ensure('courtesy@example.com');

        $this->users->setPremiumUntil($id, 1_500);

        self::assertSame(
            Entitlement::PREMIUM,
            Entitlement::forUser($this->users->findById($id), 1_400)
        );
        self::assertSame(
            Entitlement::FREE,
            Entitlement::forUser($this->users->findById($id), 1_600),
            'the grant expires on its own'
        );
    }
}
