<?php

declare(strict_types=1);

namespace Data2Md\Runtime;

/**
 * Caps how many conversions run at once. acquire() returns a Slot as soon
 * as one is free; if every slot is taken it polls until one frees up or
 * maxWaitSeconds elapses, then throws QueueTimeoutException. Slots are
 * flock()s on open file handles, so a process that dies while holding one
 * releases it automatically — the lock never outlives its holder.
 */
final class ConversionSemaphore
{
    private const POLL_MICROSECONDS = 100_000;

    public function __construct(
        private readonly string $lockDir,
        private readonly int $slots,
        private readonly int $maxWaitSeconds,
    ) {
        if ($slots < 1) {
            throw new \InvalidArgumentException('Semaphore needs at least one slot.');
        }

        if (!is_dir($lockDir) && !mkdir($lockDir, 0750, true) && !is_dir($lockDir)) {
            throw new \RuntimeException("Cannot create semaphore directory: $lockDir");
        }
    }

    public function acquire(): Slot
    {
        $started  = microtime(true);
        $deadline = $started + $this->maxWaitSeconds;

        // The deadline check comes after the sweep, so even maxWaitSeconds = 0
        // still gets one full pass over the slots before giving up.
        do {
            for ($index = 0; $index < $this->slots; $index++) {
                $handle = fopen($this->lockDir . '/slot-' . $index . '.lock', 'c');

                if ($handle === false) {
                    continue;
                }

                if (flock($handle, LOCK_EX | LOCK_NB)) {
                    return new Slot(
                        $index,
                        $handle,
                        (int) round((microtime(true) - $started) * 1000),
                    );
                }

                fclose($handle);
            }

            if (microtime(true) >= $deadline) {
                break;
            }

            usleep(self::POLL_MICROSECONDS);
        } while (true);

        throw new QueueTimeoutException(max(1, $this->maxWaitSeconds));
    }
}
