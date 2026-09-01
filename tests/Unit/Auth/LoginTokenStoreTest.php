<?php

declare(strict_types=1);

namespace Data2Md\Tests\Unit\Auth;

use Data2Md\Auth\LoginTokenStore;
use Data2Md\Storage\Database;
use PHPUnit\Framework\TestCase;

final class LoginTokenStoreTest extends TestCase
{
    private const TTL = 900;

    private string $dbPath;
    private Database $db;
    private LoginTokenStore $tokens;

    protected function setUp(): void
    {
        $this->dbPath = sys_get_temp_dir() . '/d2md-lt-' . bin2hex(random_bytes(6)) . '.sqlite';

        $this->db = new Database($this->dbPath);
        $this->db->migrate();

        $this->tokens = new LoginTokenStore($this->db, self::TTL);
    }

    protected function tearDown(): void
    {
        foreach ([$this->dbPath, $this->dbPath . '-wal', $this->dbPath . '-shm'] as $file) {
            if (file_exists($file)) {
                unlink($file);
            }
        }
    }

    public function testIssuedTokenIsHighEntropyHex(): void
    {
        $raw = $this->tokens->issue('alice@example.com');

        self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $raw);
    }

    /** Only the hash is stored, so a leak of the table yields nothing usable. */
    public function testRawTokenIsNotStored(): void
    {
        $raw = $this->tokens->issue('alice@example.com');

        $stored = $this->db->pdo()->query('SELECT token_hash FROM login_tokens')->fetchColumn();

        self::assertNotSame($raw, $stored);
        self::assertSame(hash('sha256', $raw), $stored);
    }

    public function testConsumeReturnsTheAddressOnce(): void
    {
        $raw = $this->tokens->issue('Alice@Example.com');

        self::assertSame('alice@example.com', $this->tokens->consume($raw));
        self::assertNull($this->tokens->consume($raw), 'a link is single use');
    }

    public function testUnknownTokenIsRejected(): void
    {
        self::assertNull($this->tokens->consume(bin2hex(random_bytes(32))));
    }

    public function testExpiredTokenIsRejected(): void
    {
        $issuedAt = 1_700_000_000;
        $raw      = $this->tokens->issue('alice@example.com', $issuedAt);

        self::assertNull($this->tokens->consume($raw, $issuedAt + self::TTL + 1));
        self::assertSame('alice@example.com', $this->tokens->consume($raw, $issuedAt + self::TTL - 1));
    }

    public function testPeekDoesNotConsume(): void
    {
        $raw = $this->tokens->issue('alice@example.com');

        self::assertSame('alice@example.com', $this->tokens->peekEmail($raw));
        self::assertSame('alice@example.com', $this->tokens->peekEmail($raw));
        self::assertSame('alice@example.com', $this->tokens->consume($raw));
        self::assertNull($this->tokens->peekEmail($raw), 'consumed tokens stop peeking');
    }

    /**
     * The whole point of doing this in one UPDATE: two requests racing on the
     * same link must produce exactly one winner.
     */
    public function testConcurrentConsumersProduceOneWinner(): void
    {
        $raw      = $this->tokens->issue('alice@example.com');
        $autoload = dirname(__DIR__, 3) . '/vendor/autoload.php';
        $script   = sprintf(
            'require %s;'
            . ' $d = new Data2Md\Storage\Database(%s);'
            . ' $s = new Data2Md\Auth\LoginTokenStore($d, 900);'
            . ' echo $s->consume(%s) === null ? "lost" : "won";',
            var_export($autoload, true),
            var_export($this->dbPath, true),
            var_export($raw, true)
        );

        $procs = $pipes = [];

        for ($i = 0; $i < 5; $i++) {
            $procs[$i] = proc_open(
                [PHP_BINARY, '-d', 'display_errors=stderr', '-r', $script],
                [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
                $pipes[$i]
            );
            self::assertIsResource($procs[$i]);
        }

        $won = 0;

        foreach ($procs as $i => $proc) {
            $stdout = trim((string) stream_get_contents($pipes[$i][1]));
            $stderr = trim((string) stream_get_contents($pipes[$i][2]));
            fclose($pipes[$i][1]);
            fclose($pipes[$i][2]);

            self::assertSame(0, proc_close($proc), "worker $i failed: $stderr");
            $won += $stdout === 'won' ? 1 : 0;
        }

        self::assertSame(1, $won, 'exactly one consumer may win');
    }

    public function testSiblingTokensAreInvalidatedOnUse(): void
    {
        $first  = $this->tokens->issue('alice@example.com');
        $second = $this->tokens->issue('alice@example.com');
        $other  = $this->tokens->issue('bob@example.com');

        $invalidated = $this->tokens->invalidateOthersFor('alice@example.com', LoginTokenStore::hash($second));

        self::assertSame(1, $invalidated);
        self::assertNull($this->tokens->consume($first), 'the older link stops working');
        self::assertSame('alice@example.com', $this->tokens->consume($second));
        self::assertSame('bob@example.com', $this->tokens->consume($other), 'another account is untouched');
    }

    /**
     * Purge on expiry, not on use: the row is the only record that a token was
     * spent, so removing a used one inside its TTL would re-enable replay.
     */
    public function testPurgeKeepsUsedTokensUntilTheyExpire(): void
    {
        $issuedAt = 1_700_000_000;
        $raw      = $this->tokens->issue('alice@example.com', $issuedAt);
        $this->tokens->consume($raw, $issuedAt + 1);

        self::assertSame(0, $this->tokens->purgeExpired($issuedAt + self::TTL - 1));
        self::assertSame(1, $this->tokens->count());
        self::assertNull($this->tokens->consume($raw, $issuedAt + 2), 'still recorded as spent');

        self::assertSame(1, $this->tokens->purgeExpired($issuedAt + self::TTL + 1));
        self::assertSame(0, $this->tokens->count());
    }
}
