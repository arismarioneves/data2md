<?php

declare(strict_types=1);

namespace Data2Md\Runtime;

/**
 * Runs a child process with a deadline and low scheduling priority.
 *
 * The command is passed as an array, so there is no shell and nothing to quote.
 * If the process is still running when the deadline passes, it is sent SIGTERM
 * and, if that is ignored, SIGKILL.
 */
final class Process
{
    private const POLL_MICROSECONDS = 20_000;
    private const SIGTERM_GRACE     = 5.0;

    /** @param list<string> $priorityPrefix */
    public function __construct(
        private readonly int $timeoutSeconds,
        private readonly array $priorityPrefix = [],
    ) {
    }

    /**
     * Raising niceness needs no privilege; lowering it does, so only positive
     * values are usable by an unprivileged worker. ionice class 2 priority 7
     * is the lowest best-effort priority; class 3 (idle) would need privilege
     * the caller does not have.
     *
     * @return list<string>
     */
    public static function priorityPrefix(int $nice): array
    {
        return ['nice', '-n', (string) $nice, 'ionice', '-c', '2', '-n', '7'];
    }

    /** @param list<string> $command */
    public function run(array $command): ProcessResult
    {
        if ($command === []) {
            throw new \InvalidArgumentException('Command must not be empty.');
        }

        $full = [...$this->priorityPrefix, ...$command];

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $pipes   = [];
        $process = @proc_open($full, $descriptors, $pipes);

        if (!is_resource($process)) {
            return new ProcessResult(127, '', 'Failed to start: ' . $full[0], false);
        }

        fclose($pipes[0]);
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        $stdout   = '';
        $stderr   = '';
        $started  = microtime(true);
        $timedOut = false;
        $exitCode = -1;

        while (true) {
            $status = proc_get_status($process);

            // Drained on every pass, not just after exit: a pipe that fills up
            // blocks the child, so waiting until the end to read it deadlocks
            // on large output.
            $stdout .= (string) stream_get_contents($pipes[1]);
            $stderr .= (string) stream_get_contents($pipes[2]);

            if (!$status['running']) {
                // proc_get_status() reports the real exit code only on the
                // first call after the process ends; later calls return -1.
                $exitCode = $status['exitcode'];
                break;
            }

            if ((microtime(true) - $started) >= $this->timeoutSeconds) {
                $timedOut = true;
                $exitCode = $this->terminate($process, $pipes, $stdout, $stderr);
                break;
            }

            usleep(self::POLL_MICROSECONDS);
        }

        $stdout .= (string) stream_get_contents($pipes[1]);
        $stderr .= (string) stream_get_contents($pipes[2]);

        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($process);

        return new ProcessResult($exitCode, $stdout, $stderr, $timedOut);
    }

    /**
     * Sends SIGTERM and waits up to SIGTERM_GRACE seconds for the process to
     * exit, then sends SIGKILL and waits the same grace period again.
     *
     * @param resource $process
     * @param array<int,resource> $pipes
     */
    private function terminate($process, array $pipes, string &$stdout, string &$stderr): int
    {
        proc_terminate($process, 15); // SIGTERM

        $deadline = microtime(true) + self::SIGTERM_GRACE;

        while (microtime(true) < $deadline) {
            $status = proc_get_status($process);

            $stdout .= (string) stream_get_contents($pipes[1]);
            $stderr .= (string) stream_get_contents($pipes[2]);

            if (!$status['running']) {
                return $status['exitcode'];
            }

            usleep(self::POLL_MICROSECONDS);
        }

        proc_terminate($process, 9); // SIGKILL

        $deadline = microtime(true) + self::SIGTERM_GRACE;
        while (microtime(true) < $deadline) {
            $status = proc_get_status($process);
            if (!$status['running']) {
                return $status['exitcode'];
            }
            usleep(self::POLL_MICROSECONDS);
        }

        return -1;
    }
}
