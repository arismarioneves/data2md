<?php

declare(strict_types=1);

namespace Data2Md\Tests\Integration;

use Data2Md\Runtime\ConversionSemaphore;
use Data2Md\Runtime\QueueTimeoutException;
use PHPUnit\Framework\TestCase;

final class ConversionSemaphoreTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/d2md-sem-' . bin2hex(random_bytes(6));
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir . '/*') ?: [] as $file) {
            unlink($file);
        }
        if (is_dir($this->dir)) {
            rmdir($this->dir);
        }
    }

    public function testGrantsUpToTheSlotCount(): void
    {
        $semaphore = new ConversionSemaphore($this->dir, 2, 0);

        $first  = $semaphore->acquire();
        $second = $semaphore->acquire();

        self::assertNotSame($first->index(), $second->index());

        $first->release();
        $second->release();
    }

    public function testRejectsBeyondTheSlotCountWhenNotWillingToWait(): void
    {
        $semaphore = new ConversionSemaphore($this->dir, 1, 0);
        $held      = $semaphore->acquire();

        try {
            $this->expectException(QueueTimeoutException::class);
            $semaphore->acquire();
        } finally {
            $held->release();
        }
    }

    public function testReleaseMakesTheSlotAvailableAgain(): void
    {
        $semaphore = new ConversionSemaphore($this->dir, 1, 0);

        $first = $semaphore->acquire();
        $first->release();

        $second = $semaphore->acquire();
        self::assertSame(0, $second->index());
        $second->release();
    }

    public function testWaitsUpToTheDeadlineBeforeGivingUp(): void
    {
        $semaphore = new ConversionSemaphore($this->dir, 1, 1);
        $held      = $semaphore->acquire();

        $start = microtime(true);

        try {
            $semaphore->acquire();
            self::fail('expected QueueTimeoutException');
        } catch (QueueTimeoutException $e) {
            $elapsed = microtime(true) - $start;

            self::assertGreaterThanOrEqual(0.9, $elapsed, 'must actually queue, not fail instantly');
            self::assertLessThan(3.0, $elapsed);
            self::assertGreaterThan(0, $e->retryAfter());
        } finally {
            $held->release();
        }
    }

    public function testSlotIsFreedWhenTheHoldingProcessDies(): void
    {
        $semaphore = new ConversionSemaphore($this->dir, 1, 0);
        $script    = $this->childScript();

        // Child takes the only slot and is killed without releasing it.
        $descriptors = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $child = proc_open(['php', $script, $this->dir], $descriptors, $pipes);
        self::assertIsResource($child);

        fclose($pipes[0]);
        // Wait for the child to report that it holds the slot.
        self::assertSame("held\n", fgets($pipes[1]));

        try {
            $semaphore->acquire();
            self::fail('slot should be held by the child');
        } catch (QueueTimeoutException) {
            // expected
        }

        proc_terminate($child, 9);
        proc_close($child);
        // proc_close() already closed the pipes here (PHP 8.3, Linux); guard
        // against fclose()'ing an already-invalid resource.
        is_resource($pipes[1]) && fclose($pipes[1]);
        is_resource($pipes[2]) && fclose($pipes[2]);

        // The OS drops the flock when the process dies — no slot leaks.
        $recovered = $semaphore->acquire();
        self::assertSame(0, $recovered->index());
        $recovered->release();

        unlink($script);
    }

    public function testWaitedMsIsRecorded(): void
    {
        $semaphore = new ConversionSemaphore($this->dir, 1, 0);
        $slot      = $semaphore->acquire();

        self::assertGreaterThanOrEqual(0, $slot->waitedMs());
        self::assertLessThan(1000, $slot->waitedMs());

        $slot->release();
    }

    public function testDoubleReleaseIsHarmless(): void
    {
        $slot = (new ConversionSemaphore($this->dir, 1, 0))->acquire();

        $slot->release();
        $slot->release();

        self::assertTrue(true);
    }

    private function childScript(): string
    {
        $path = $this->dir . '-child.php';
        $autoload = dirname(__DIR__, 2) . '/vendor/autoload.php';

        file_put_contents($path, <<<PHP
        <?php
        require '{$autoload}';
        \$semaphore = new \\Data2Md\\Runtime\\ConversionSemaphore(\$argv[1], 1, 0);
        \$slot = \$semaphore->acquire();
        echo "held\\n";
        flush();
        sleep(60);
        PHP);

        return $path;
    }
}
