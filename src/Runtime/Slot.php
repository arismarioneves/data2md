<?php

declare(strict_types=1);

namespace Data2Md\Runtime;

/**
 * A held conversion slot, backed by a flock() on an open file handle. If the
 * holding process dies, the OS drops the lock with it, so the slot is never
 * left held by nothing.
 */
final class Slot
{
    /** @param resource $handle */
    public function __construct(
        private readonly int $index,
        private $handle,
        private readonly int $waitedMs,
    ) {
    }

    public function index(): int
    {
        return $this->index;
    }

    public function waitedMs(): int
    {
        return $this->waitedMs;
    }

    /** Safe to call more than once; only the first call has any effect. */
    public function release(): void
    {
        if (!is_resource($this->handle)) {
            return;
        }

        flock($this->handle, LOCK_UN);
        fclose($this->handle);
    }

    public function __destruct()
    {
        $this->release();
    }
}
