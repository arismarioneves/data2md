<?php

declare(strict_types=1);

namespace Data2Md\Storage;

/**
 * Tells an operator, at every boot, whether the data directory is the same one
 * the last container used.
 *
 * The Dockerfile declares a VOLUME, which means Docker mounts an *anonymous*
 * volume when the host configures none. A deploy then gets a brand new, empty
 * directory: accounts gone, conversions gone, and nothing anywhere says so --
 * every check passes, because a fresh database is a valid database.
 *
 * A marker written on first boot turns that into one loud line. The only false
 * alarm is the genuine first deploy, which happens once.
 */
final class StorageIdentity
{
    private const MARKER = '/db/.storage-id';

    public function __construct(private readonly string $dataDir)
    {
    }

    /**
     * @return array{fresh:bool,id:string,since:int}
     */
    public function markAndRead(?int $now = null): array
    {
        $now  = $now ?? time();
        $path = $this->dataDir . self::MARKER;

        $existing = is_file($path) ? trim((string) file_get_contents($path)) : '';

        if (preg_match('/^([0-9a-f]{16}) (\d+)$/', $existing, $m) === 1) {
            return ['fresh' => false, 'id' => $m[1], 'since' => (int) $m[2]];
        }

        $id = bin2hex(random_bytes(8));

        // Best effort: a directory this cannot be written to is itself worth
        // reporting, and the caller reports what it got back either way.
        @file_put_contents($path, $id . ' ' . $now . "\n");
        @chmod($path, 0640);

        return ['fresh' => true, 'id' => $id, 'since' => $now];
    }
}
