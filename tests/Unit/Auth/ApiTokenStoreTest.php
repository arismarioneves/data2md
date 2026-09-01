<?php

declare(strict_types=1);

namespace Data2Md\Tests\Unit\Auth;

use Data2Md\Auth\ApiTokenStore;
use Data2Md\Auth\UserStore;
use Data2Md\Storage\Database;
use PHPUnit\Framework\TestCase;

final class ApiTokenStoreTest extends TestCase
{
    private string $dbPath;
    private Database $db;
    private ApiTokenStore $tokens;
    private UserStore $users;

    protected function setUp(): void
    {
        $this->dbPath = sys_get_temp_dir() . '/d2md-tokens-' . bin2hex(random_bytes(6)) . '.sqlite';

        $this->db = new Database($this->dbPath);
        $this->db->migrate();

        $this->tokens = new ApiTokenStore($this->db);
        $this->users  = new UserStore($this->db);
    }

    protected function tearDown(): void
    {
        foreach ([$this->dbPath, $this->dbPath . '-wal', $this->dbPath . '-shm'] as $file) {
            if (file_exists($file)) {
                unlink($file);
            }
        }
    }

    public function testIssuedTokenHasThePrefixedShape(): void
    {
        $raw = $this->tokens->issue($this->users->ensure('a@example.com'));

        self::assertStringStartsWith('d2md_', $raw);
        self::assertMatchesRegularExpression('/^d2md_[a-f0-9]{48}$/', $raw);
        self::assertTrue(ApiTokenStore::hasTokenShape($raw));
    }

    /** A leaked database must not hand anyone a working credential. */
    public function testOnlyTheHashIsStored(): void
    {
        $raw = $this->tokens->issue($this->users->ensure('a@example.com'));

        $row = $this->db->pdo()->query('SELECT * FROM api_tokens')->fetch();

        self::assertSame(hash('sha256', $raw), $row['token_hash']);
        self::assertStringNotContainsString(substr($raw, 5), (string) json_encode($row));
    }

    public function testStoredMaskIsEnoughToRecogniseAndNotToUse(): void
    {
        $userId = $this->users->ensure('a@example.com');
        $raw    = $this->tokens->issue($userId);

        $described = $this->tokens->describeFor($userId);

        self::assertSame(substr($raw, 0, 9), $described['prefix']);
        self::assertSame(substr($raw, -4), $described['last4']);
    }

    public function testAuthenticateReturnsTheAccount(): void
    {
        $userId = $this->users->ensure('owner@example.com');
        $raw    = $this->tokens->issue($userId);

        $user = $this->tokens->authenticate($raw);

        self::assertNotNull($user);
        self::assertSame($userId, (int) $user['id']);
        self::assertSame('owner@example.com', $user['email']);
    }

    public function testAuthenticateRejectsAnUnknownToken(): void
    {
        $this->tokens->issue($this->users->ensure('a@example.com'));

        self::assertNull($this->tokens->authenticate('d2md_' . str_repeat('0', 48)));
    }

    /** @dataProvider malformedTokens */
    public function testAuthenticateRejectsMalformedInputWithoutQuerying(string $candidate): void
    {
        self::assertFalse(ApiTokenStore::hasTokenShape($candidate));
        self::assertNull($this->tokens->authenticate($candidate));
    }

    public static function malformedTokens(): array
    {
        return [
            'empty'         => [''],
            'no prefix'     => [str_repeat('a', 48)],
            'too short'     => ['d2md_' . str_repeat('a', 47)],
            'too long'      => ['d2md_' . str_repeat('a', 49)],
            'not hex'       => ['d2md_' . str_repeat('z', 48)],
            'uppercase hex' => ['d2md_' . str_repeat('A', 48)],
            'wrong prefix'  => ['sk_live_' . str_repeat('a', 48)],
            'quoted sql'    => ['d2md_" OR 1=1 --'],
            'trailing space' => ['d2md_' . str_repeat('a', 48) . ' '],
        ];
    }

    /** Rotation is a replacement, so the previous credential stops working. */
    public function testRotatingReplacesTheTokenInPlace(): void
    {
        $userId = $this->users->ensure('a@example.com');

        $first  = $this->tokens->issue($userId);
        $second = $this->tokens->issue($userId);

        self::assertNotSame($first, $second);
        self::assertSame(1, $this->tokens->count(), 'one row per account');
        self::assertNull($this->tokens->authenticate($first), 'the replaced token is dead');
        self::assertNotNull($this->tokens->authenticate($second));
    }

    public function testRotationIsRecorded(): void
    {
        $userId = $this->users->ensure('a@example.com');

        $this->tokens->issue($userId, 1_000);
        self::assertNull($this->tokens->describeFor($userId)['rotated_at']);

        $this->tokens->issue($userId, 2_000);

        $described = $this->tokens->describeFor($userId);
        self::assertSame(1_000, (int) $described['created_at'], 'the account kept its original date');
        self::assertSame(2_000, (int) $described['rotated_at']);
    }

    /** A rotated token must not inherit the replaced one's usage. */
    public function testRotationClearsLastUsed(): void
    {
        $userId = $this->users->ensure('a@example.com');
        $raw    = $this->tokens->issue($userId, 1_000);

        $this->tokens->authenticate($raw, 1_500);
        self::assertSame(1_500, (int) $this->tokens->describeFor($userId)['last_used_at']);

        $this->tokens->issue($userId, 2_000);

        self::assertNull($this->tokens->describeFor($userId)['last_used_at']);
    }

    public function testFirstUseIsRecorded(): void
    {
        $userId = $this->users->ensure('a@example.com');
        $raw    = $this->tokens->issue($userId, 1_000);

        self::assertNull($this->tokens->describeFor($userId)['last_used_at']);

        $this->tokens->authenticate($raw, 1_200);

        self::assertSame(1_200, (int) $this->tokens->describeFor($userId)['last_used_at']);
    }

    /**
     * Every API call would otherwise take the WAL write lock to move a
     * timestamp by a second.
     */
    public function testUsageIsNotRewrittenOnEveryCall(): void
    {
        $userId = $this->users->ensure('a@example.com');
        $raw    = $this->tokens->issue($userId, 1_000);

        $this->tokens->authenticate($raw, 1_200);
        $this->tokens->authenticate($raw, 1_800);

        self::assertSame(1_200, (int) $this->tokens->describeFor($userId)['last_used_at'], 'within the threshold');

        $this->tokens->authenticate($raw, 1_200 + 3_600);

        self::assertSame(4_800, (int) $this->tokens->describeFor($userId)['last_used_at']);
    }

    public function testTokensAreScopedToTheirOwnAccount(): void
    {
        $alice = $this->users->ensure('alice@example.com');
        $bob   = $this->users->ensure('bob@example.com');

        $aliceToken = $this->tokens->issue($alice);
        $bobToken   = $this->tokens->issue($bob);

        self::assertSame($alice, (int) $this->tokens->authenticate($aliceToken)['id']);
        self::assertSame($bob, (int) $this->tokens->authenticate($bobToken)['id']);
        self::assertSame(2, $this->tokens->count());
    }

    public function testRevokeRemovesTheToken(): void
    {
        $userId = $this->users->ensure('a@example.com');
        $raw    = $this->tokens->issue($userId);

        self::assertTrue($this->tokens->revokeFor($userId));
        self::assertNull($this->tokens->authenticate($raw));
        self::assertNull($this->tokens->describeFor($userId));
        self::assertFalse($this->tokens->revokeFor($userId), 'nothing left to revoke');
    }

    public function testAccountWithoutATokenDescribesAsNothing(): void
    {
        self::assertNull($this->tokens->describeFor($this->users->ensure('a@example.com')));
    }

    /** Deleting an account must not leave a credential that still authenticates. */
    public function testDeletingTheAccountCascades(): void
    {
        $userId = $this->users->ensure('a@example.com');
        $raw    = $this->tokens->issue($userId);

        $this->users->delete($userId);

        self::assertSame(0, $this->tokens->count());
        self::assertNull($this->tokens->authenticate($raw));
    }
}
