<?php

declare(strict_types=1);

namespace Data2Md\Tests\Integration\Support;

/**
 * Stream wrapper backed by a real file that accepts a fixed number of writes
 * and reports every write after that as failed, to reproduce a write error
 * partway through producing output. unlink() removes the backing file, so a
 * test can observe whether the caller cleaned up after the failure.
 */
final class FailingWriteStream
{
    public const SCHEME = 'failing-write';

    public static int $allowedWrites = 0;

    /** Assigned by the engine; declared to avoid a dynamic-property notice. */
    public $context;

    /** @var resource */
    private $handle;

    private int $writes = 0;

    public static function url(string $backingPath): string
    {
        return self::SCHEME . '://' . $backingPath;
    }

    public static function register(): void
    {
        if (!in_array(self::SCHEME, stream_get_wrappers(), true)) {
            stream_wrapper_register(self::SCHEME, self::class);
        }
    }

    public function stream_open(string $path, string $mode): bool
    {
        $handle = fopen(self::backingPath($path), $mode);
        if ($handle === false) {
            return false;
        }

        $this->handle = $handle;

        return true;
    }

    /** @return int|false */
    public function stream_write(string $data)
    {
        $this->writes++;

        if ($this->writes > self::$allowedWrites) {
            return false;
        }

        return fwrite($this->handle, $data);
    }

    public function stream_close(): void
    {
        fclose($this->handle);
    }

    public function stream_eof(): bool
    {
        return feof($this->handle);
    }

    public function unlink(string $path): bool
    {
        return unlink(self::backingPath($path));
    }

    private static function backingPath(string $url): string
    {
        return substr($url, strlen(self::SCHEME . '://'));
    }
}
