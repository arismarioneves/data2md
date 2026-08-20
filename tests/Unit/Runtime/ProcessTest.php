<?php

declare(strict_types=1);

namespace Data2Md\Tests\Unit\Runtime;

use Data2Md\Runtime\Process;
use PHPUnit\Framework\TestCase;

final class ProcessTest extends TestCase
{
    public function testCapturesStdoutAndExitCode(): void
    {
        $result = (new Process(10))->run(['echo', 'hello']);

        self::assertSame(0, $result->exitCode);
        self::assertSame("hello\n", $result->stdout);
        self::assertFalse($result->timedOut);
    }

    public function testCapturesStderrAndNonZeroExit(): void
    {
        $result = (new Process(10))->run(['sh', '-c', 'echo oops >&2; exit 3']);

        self::assertSame(3, $result->exitCode);
        self::assertSame("oops\n", $result->stderr);
        self::assertFalse($result->timedOut);
    }

    public function testKillsAProcessThatOverrunsTheTimeout(): void
    {
        $start  = microtime(true);
        $result = (new Process(1))->run(['sleep', '30']);
        $elapsed = microtime(true) - $start;

        self::assertTrue($result->timedOut, 'a hung process must be killed, not waited on');
        self::assertLessThan(10.0, $elapsed);
        self::assertNotSame(0, $result->exitCode);
    }

    public function testSurvivesAProcessIgnoringSigterm(): void
    {
        $result = (new Process(1))->run(['sh', '-c', 'trap "" TERM; sleep 30']);

        self::assertTrue($result->timedOut, 'SIGKILL must follow when SIGTERM is ignored');
    }

    public function testLargeOutputIsNotTruncatedOrDeadlocked(): void
    {
        // A blocking read on a full pipe is the classic proc_open deadlock.
        $result = (new Process(30))->run(['sh', '-c', 'head -c 2000000 /dev/zero | tr "\\0" "a"']);

        self::assertSame(0, $result->exitCode);
        self::assertSame(2_000_000, strlen($result->stdout));
    }

    public function testMissingBinaryDoesNotHang(): void
    {
        $result = (new Process(5))->run(['this-binary-does-not-exist-9x7']);

        self::assertNotSame(0, $result->exitCode);
        self::assertFalse($result->timedOut);
    }

    public function testPriorityPrefixIsPrependedAndRuns(): void
    {
        $process = new Process(10, Process::priorityPrefix(15));

        $result = $process->run(['sh', '-c', 'nice']);

        self::assertSame(0, $result->exitCode, 'stderr: ' . $result->stderr);
        self::assertSame(15, (int) trim($result->stdout), 'nice level must reach the child');
    }

    public function testPriorityPrefixShape(): void
    {
        self::assertSame(
            ['nice', '-n', '15', 'ionice', '-c', '2', '-n', '7'],
            Process::priorityPrefix(15),
            'ionice class 3 (idle) needs privilege; class 2 priority 7 does not'
        );
    }

    public function testEmptyCommandIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new Process(10))->run([]);
    }
}
