<?php

declare(strict_types=1);

namespace Data2Md\Storage;

/**
 * What kind of storage the data directory actually sits on.
 *
 * The Dockerfile declares a VOLUME, so Docker always mounts *something* there.
 * When the host configures nothing, that something is an anonymous volume: a
 * new, empty one for every container, discarded with the old container. Every
 * check still passes, because an empty database is a valid database.
 *
 * Docker names anonymous volumes with a 64-character hex id and named ones with
 * whatever the host called them, and the difference is visible from inside the
 * container in /proc/self/mountinfo. That turns "this directory looks new" into
 * "this volume cannot survive a redeploy", reportable on the first boot rather
 * than after the first loss.
 */
final class StorageMount
{
    public const ANONYMOUS = 'anonymous';
    public const NAMED     = 'named';
    public const BIND      = 'bind';
    public const CONTAINER = 'container';
    public const UNKNOWN   = 'unknown';

    public function __construct(
        private readonly string $dataDir,
        private readonly string $mountInfoPath = '/proc/self/mountinfo',
    ) {
    }

    /** True only when this app can say the storage will not survive. */
    public function isEphemeral(): bool
    {
        $kind = $this->kind();

        return $kind === self::ANONYMOUS || $kind === self::CONTAINER;
    }

    public function kind(): string
    {
        return $this->describe()['kind'];
    }

    /** @return array{kind:string,source:?string} */
    public function describe(): array
    {
        if (!is_readable($this->mountInfoPath)) {
            // Not Linux, or a kernel without it. The marker file still applies.
            return ['kind' => self::UNKNOWN, 'source' => null];
        }

        $source = $this->sourceFor((string) file_get_contents($this->mountInfoPath));

        if ($source === null) {
            // Nothing is mounted there, so it lives in the container's own
            // writable layer and goes with the container.
            return ['kind' => self::CONTAINER, 'source' => null];
        }

        // Docker gives an anonymous volume a 64-character hex name; anything
        // else was named by whoever set it up.
        if (preg_match('#/volumes/([0-9a-f]{64})/_data$#', $source) === 1) {
            return ['kind' => self::ANONYMOUS, 'source' => $source];
        }

        if (preg_match('#/volumes/[^/]+/_data$#', $source) === 1) {
            return ['kind' => self::NAMED, 'source' => $source];
        }

        return ['kind' => self::BIND, 'source' => $source];
    }

    /**
     * The mount root backing the data directory, from the deepest mount point
     * that contains it.
     *
     * Fields are: id, parent, dev, root, mountpoint, ... — space separated, and
     * the kernel escapes spaces in paths as \040.
     */
    private function sourceFor(string $mountInfo): ?string
    {
        $target = rtrim($this->dataDir, '/');
        $best   = null;
        $depth  = -1;

        foreach (explode("\n", $mountInfo) as $line) {
            $fields = explode(' ', trim($line));

            if (count($fields) < 5) {
                continue;
            }

            $root       = str_replace('\040', ' ', $fields[3]);
            $mountPoint = str_replace('\040', ' ', $fields[4]);

            // rtrim would turn the root mount into an empty string, which then
            // prefix-matches everything and reports the overlay as a bind.
            $mountPoint = $mountPoint === '/' ? '/' : rtrim($mountPoint, '/');

            if ($mountPoint !== $target && !str_starts_with($target, $mountPoint . '/')) {
                continue;
            }

            // A later, deeper mount shadows an earlier shallower one.
            $here = substr_count($mountPoint, '/');

            if ($here >= $depth) {
                $depth = $here;
                $best  = $mountPoint === '/' ? null : $root;
            }
        }

        return $best;
    }
}
