<?php

declare(strict_types=1);

namespace Data2Md\Tests\Unit\Storage;

use Data2Md\Storage\StorageMount;
use PHPUnit\Framework\TestCase;

final class StorageMountTest extends TestCase
{
    private string $file;

    protected function setUp(): void
    {
        $this->file = sys_get_temp_dir() . '/d2md-mountinfo-' . bin2hex(random_bytes(6));
    }

    protected function tearDown(): void
    {
        if (is_file($this->file)) {
            unlink($this->file);
        }
    }

    private function mount(string $mountInfo, string $dir = '/var/lib/data2md'): StorageMount
    {
        file_put_contents($this->file, $mountInfo);

        return new StorageMount($dir, $this->file);
    }

    /**
     * Both lines are real, captured from containers on the same host: one with
     * a named volume, one with none.
     */
    private const NAMED = '497 485 8:48 /data/docker/volumes/data2md_data2md-data/_data /var/lib/data2md rw,relatime master:31 - ext4 /dev/sdd rw';
    private const ANON  = '584 572 8:48 /data/docker/volumes/52f06932426db583ddb23abb8b6d1ce83ae33e7f3f952c8e9ed06a416a247675/_data /var/lib/data2md rw,relatime master:31 - ext4 /dev/sdd rw';
    private const ROOT  = '470 469 0:96 / / rw,relatime - overlay overlay rw';

    public function testANamedVolumePersists(): void
    {
        $mount = $this->mount(self::ROOT . "\n" . self::NAMED);

        self::assertSame(StorageMount::NAMED, $mount->kind());
        self::assertFalse($mount->isEphemeral());
    }

    /** The failure this class exists for, and it is stateable on the first boot. */
    public function testAnAnonymousVolumeDoesNotPersist(): void
    {
        $mount = $this->mount(self::ROOT . "\n" . self::ANON);

        self::assertSame(StorageMount::ANONYMOUS, $mount->kind());
        self::assertTrue($mount->isEphemeral());
    }

    /** Nothing mounted means the container's own writable layer. */
    public function testNoMountMeansTheContainerLayer(): void
    {
        $mount = $this->mount(self::ROOT);

        self::assertSame(StorageMount::CONTAINER, $mount->kind());
        self::assertTrue($mount->isEphemeral());
    }

    public function testABindMountIsRecognised(): void
    {
        $line = '500 485 8:48 /srv/data2md /var/lib/data2md rw,relatime - ext4 /dev/sdd rw';

        $mount = $this->mount(self::ROOT . "\n" . $line);

        self::assertSame(StorageMount::BIND, $mount->kind());
        self::assertFalse($mount->isEphemeral(), 'a host path is the operator saying where it goes');
    }

    /** A volume mounted above the data directory still covers it. */
    public function testAMountOnAParentDirectoryCounts(): void
    {
        $line = '500 485 8:48 /data/docker/volumes/mydata/_data /var/lib rw,relatime - ext4 /dev/sdd rw';

        self::assertSame(StorageMount::NAMED, $this->mount(self::ROOT . "\n" . $line)->kind());
    }

    /** The deeper mount wins: a volume inside a bind mount is what holds the data. */
    public function testTheDeepestMountWins(): void
    {
        $parent = '500 485 8:48 /srv/stuff /var/lib rw,relatime - ext4 /dev/sdd rw';

        $mount = $this->mount(self::ROOT . "\n" . $parent . "\n" . self::ANON);

        self::assertSame(StorageMount::ANONYMOUS, $mount->kind());
    }

    /** A sibling path must not be mistaken for the one asked about. */
    public function testAnUnrelatedMountIsIgnored(): void
    {
        $line = '500 485 8:48 /data/docker/volumes/other/_data /var/lib/data2md-other rw,relatime - ext4 /dev/sdd rw';

        self::assertSame(StorageMount::CONTAINER, $this->mount(self::ROOT . "\n" . $line)->kind());
    }

    /** The kernel escapes spaces; a path is not a reason to misreport. */
    public function testEscapedSpacesInPathsAreHandled(): void
    {
        $line = '500 485 8:48 /data/docker/volumes/my\040vol/_data /var/lib/data2md rw,relatime - ext4 /dev/sdd rw';

        self::assertSame(StorageMount::NAMED, $this->mount(self::ROOT . "\n" . $line)->kind());
    }

    /** Nothing to read is not evidence of anything; the marker file still applies. */
    public function testAnAbsentMountInfoIsUnknownRatherThanEphemeral(): void
    {
        $mount = new StorageMount('/var/lib/data2md', '/nonexistent/mountinfo');

        self::assertSame(StorageMount::UNKNOWN, $mount->kind());
        self::assertFalse($mount->isEphemeral(), 'never accuse without evidence');
    }

    public function testTrailingSlashesDoNotMatter(): void
    {
        $mount = $this->mount(self::ROOT . "\n" . self::NAMED, '/var/lib/data2md/');

        self::assertSame(StorageMount::NAMED, $mount->kind());
    }
}
