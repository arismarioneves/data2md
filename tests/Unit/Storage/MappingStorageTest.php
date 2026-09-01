<?php

declare(strict_types=1);

namespace Data2Md\Tests\Unit\Storage;

use Data2Md\Auth\UserStore;
use Data2Md\Storage\Database;
use Data2Md\Storage\MappingStorage;
use PHPUnit\Framework\TestCase;

final class MappingStorageTest extends TestCase
{
    private string $dbPath;
    private Database $db;
    private MappingStorage $storage;

    protected function setUp(): void
    {
        $this->dbPath = sys_get_temp_dir() . '/d2md-map-' . bin2hex(random_bytes(6)) . '.sqlite';
        $this->db = new Database($this->dbPath);
        $this->db->migrate();
        $this->storage = new MappingStorage($this->db);
    }

    protected function tearDown(): void
    {
        foreach ([$this->dbPath, $this->dbPath . '-wal', $this->dbPath . '-shm'] as $file) {
            if (file_exists($file)) {
                unlink($file);
            }
        }
    }

    public function testSaveAndFindRoundTrip(): void
    {
        $this->storage->save('a1b2c3d4e5f6789', 'relatorio.pdf', 'pdf', 1024);

        $found = $this->storage->find('a1b2c3d4e5f6789');

        self::assertNotNull($found);
        self::assertSame('relatorio.pdf', $found['original_name']);
        self::assertSame('pdf', $found['format']);
        self::assertSame(1024, $found['size_bytes']);
        self::assertGreaterThan(0, $found['created_at']);
    }

    public function testFindReturnsNullForUnknownHash(): void
    {
        self::assertNull($this->storage->find('000000000000000'));
    }

    public function testExists(): void
    {
        self::assertFalse($this->storage->exists('a1b2c3d4e5f6789'));
        $this->storage->save('a1b2c3d4e5f6789', 'x.pdf', 'pdf', 1);
        self::assertTrue($this->storage->exists('a1b2c3d4e5f6789'));
    }

    public function testSaveIsIdempotentOnTheSameHash(): void
    {
        $now = time();
        $this->storage->save('a1b2c3d4e5f6789', 'first.pdf', 'pdf', 1, $now - 1000);
        $this->storage->save('a1b2c3d4e5f6789', 'second.pdf', 'pdf', 2, $now);

        $found = $this->storage->find('a1b2c3d4e5f6789');

        self::assertSame('second.pdf', $found['original_name']);
        self::assertSame($now, $found['created_at'], 'ON CONFLICT overwrites created_at too, since the real path never collides -- generateUniqueHash() guarantees a fresh hash before insert -- and hashesOlderThan() must key off whichever row is on record.');
    }

    public function testGeneratedHashMatchesTheValidatorPattern(): void
    {
        $hash = $this->storage->generateUniqueHash();

        self::assertMatchesRegularExpression('/^[a-f0-9]{15}$/', $hash);
        self::assertFalse($this->storage->exists($hash));
    }

    public function testGeneratedHashesAreDistinct(): void
    {
        $seen = [];
        for ($i = 0; $i < 50; $i++) {
            $hash = $this->storage->generateUniqueHash();
            $this->storage->save($hash, 'x.pdf', 'pdf', 1);
            $seen[$hash] = true;
        }
        self::assertCount(50, $seen);
    }

    public function testHashesOlderThanRespectsTheCut(): void
    {
        $now = time();
        $this->storage->save('aaaaaaaaaaaaaaa', 'old.pdf', 'pdf', 1, $now - 100);
        $this->storage->save('bbbbbbbbbbbbbbb', 'new.pdf', 'pdf', 1, $now);

        $old = $this->storage->hashesOlderThan($now - 50);

        self::assertSame(['aaaaaaaaaaaaaaa'], $old);
    }

    public function testDelete(): void
    {
        $this->storage->save('a1b2c3d4e5f6789', 'x.pdf', 'pdf', 1);
        $this->storage->delete('a1b2c3d4e5f6789');

        self::assertNull($this->storage->find('a1b2c3d4e5f6789'));
    }

    public function testAllHashes(): void
    {
        $this->storage->save('aaaaaaaaaaaaaaa', 'a.pdf', 'pdf', 1);
        $this->storage->save('bbbbbbbbbbbbbbb', 'b.pdf', 'pdf', 1);

        $all = $this->storage->allHashes();
        sort($all);

        self::assertSame(['aaaaaaaaaaaaaaa', 'bbbbbbbbbbbbbbb'], $all);
    }

    public function testRecentReturnsNewestFirst(): void
    {
        $now = time();
        $this->storage->save('aaaaaaaaaaaaaaa', 'older.pdf', 'pdf', 10, $now - 7200);
        $this->storage->save('bbbbbbbbbbbbbbb', 'newest.pdf', 'pdf', 20, $now);
        $this->storage->save('ccccccccccccccc', 'middle.pdf', 'pdf', 30, $now - 3600);

        $rows = $this->storage->recent(10);

        self::assertSame(
            ['newest.pdf', 'middle.pdf', 'older.pdf'],
            array_column($rows, 'original_name')
        );
        self::assertSame(20, $rows[0]['size_bytes']);
        self::assertSame('bbbbbbbbbbbbbbb', $rows[0]['hash']);
    }

    public function testRecentHonoursTheLimit(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->storage->save(str_repeat((string) $i, 15), "f$i.pdf", 'pdf', 1, time() - $i);
        }

        self::assertCount(2, $this->storage->recent(2));
    }

    public function testRecentClampsANonPositiveLimit(): void
    {
        $this->storage->save('aaaaaaaaaaaaaaa', 'one.pdf', 'pdf', 1);

        self::assertCount(1, $this->storage->recent(0));
        self::assertCount(1, $this->storage->recent(-5));
    }

    public function testRecentOnAnEmptyTable(): void
    {
        self::assertSame([], $this->storage->recent(10));
    }

    public function testCount(): void
    {
        self::assertSame(0, $this->storage->count());

        $this->storage->save('aaaaaaaaaaaaaaa', 'a.pdf', 'pdf', 1);
        $this->storage->save('bbbbbbbbbbbbbbb', 'b.pdf', 'pdf', 1);

        self::assertSame(2, $this->storage->count());
    }

    public function testUnicodeNamesSurvive(): void
    {
        $this->storage->save('a1b2c3d4e5f6789', 'relatório anual.pdf', 'pdf', 1);

        self::assertSame('relatório anual.pdf', $this->storage->find('a1b2c3d4e5f6789')['original_name']);
    }

    public function testConversionsAreAttributedToAnAccount(): void
    {
        $alice = (new UserStore($this->db))->ensure('alice@example.com');
        $bob   = (new UserStore($this->db))->ensure('bob@example.com');

        $this->storage->save('aaaaaaaaaaaaaaa', 'a.pdf', 'pdf', 10, 100, $alice);
        $this->storage->save('bbbbbbbbbbbbbbb', 'b.pdf', 'pdf', 20, 200, $alice);
        $this->storage->save('ccccccccccccccc', 'c.pdf', 'pdf', 30, 300, $bob);
        $this->storage->save('ddddddddddddddd', 'anon.pdf', 'pdf', 40, 400);

        self::assertSame(2, $this->storage->countForUser($alice));
        self::assertSame(1, $this->storage->countForUser($bob));

        $hashes = array_column($this->storage->forUser($alice, 10), 'hash');

        self::assertSame(['bbbbbbbbbbbbbbb', 'aaaaaaaaaaaaaaa'], $hashes, 'most recent first');
    }

    public function testForUserPaginates(): void
    {
        $alice = (new UserStore($this->db))->ensure('alice@example.com');

        foreach (range(1, 5) as $n) {
            $this->storage->save(str_repeat((string) $n, 15), "$n.pdf", 'pdf', 10, 100 + $n, $alice);
        }

        self::assertCount(2, $this->storage->forUser($alice, 2));
        self::assertSame(
            ['333333333333333'],
            array_column($this->storage->forUser($alice, 1, 2), 'hash')
        );
    }

    public function testDeleteOnlyTouchesTheOwnersRow(): void
    {
        $alice = (new UserStore($this->db))->ensure('alice@example.com');
        $bob   = (new UserStore($this->db))->ensure('bob@example.com');

        $this->storage->save('aaaaaaaaaaaaaaa', 'a.pdf', 'pdf', 10, 100, $alice);

        self::assertFalse($this->storage->deleteForUser('aaaaaaaaaaaaaaa', $bob), 'not yours');
        self::assertNotNull($this->storage->find('aaaaaaaaaaaaaaa'));

        self::assertTrue($this->storage->deleteForUser('aaaaaaaaaaaaaaa', $alice));
        self::assertNull($this->storage->find('aaaaaaaaaaaaaaa'));
    }

    /** Every conversion made before accounts existed has user_id NULL. */
    public function testDetachedRowsCanNeverBeClaimed(): void
    {
        $alice = (new UserStore($this->db))->ensure('alice@example.com');

        $this->storage->save('ddddddddddddddd', 'anon.pdf', 'pdf', 40, 400);

        self::assertFalse($this->storage->belongsTo('ddddddddddddddd', $alice));
        self::assertFalse($this->storage->deleteForUser('ddddddddddddddd', $alice));
        self::assertNotNull($this->storage->find('ddddddddddddddd'));
        self::assertSame(0, $this->storage->countForUser($alice));
    }

    /** Deleting an account leaves its markdown reachable by hash. */
    public function testDeletingTheAccountDetachesRatherThanRemoves(): void
    {
        $users = new UserStore($this->db);
        $alice = $users->ensure('alice@example.com');

        $this->storage->save('aaaaaaaaaaaaaaa', 'a.pdf', 'pdf', 10, 100, $alice);
        $users->delete($alice);

        self::assertNotNull($this->storage->find('aaaaaaaaaaaaaaa'));
        self::assertSame(
            null,
            $this->db->pdo()->query("SELECT user_id FROM mappings WHERE hash = 'aaaaaaaaaaaaaaa'")->fetchColumn()
                ?: null
        );
    }
}
