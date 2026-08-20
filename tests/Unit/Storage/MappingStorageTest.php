<?php

declare(strict_types=1);

namespace Data2Md\Tests\Unit\Storage;

use Data2Md\Storage\Database;
use Data2Md\Storage\MappingStorage;
use PHPUnit\Framework\TestCase;

final class MappingStorageTest extends TestCase
{
    private string $dbPath;
    private MappingStorage $storage;

    protected function setUp(): void
    {
        $this->dbPath = sys_get_temp_dir() . '/d2md-map-' . bin2hex(random_bytes(6)) . '.sqlite';
        $db = new Database($this->dbPath);
        $db->migrate();
        $this->storage = new MappingStorage($db);
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

    public function testUnicodeNamesSurvive(): void
    {
        $this->storage->save('a1b2c3d4e5f6789', 'relatório anual.pdf', 'pdf', 1);

        self::assertSame('relatório anual.pdf', $this->storage->find('a1b2c3d4e5f6789')['original_name']);
    }
}
