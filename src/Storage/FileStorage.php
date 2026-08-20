<?php

declare(strict_types=1);

namespace Data2Md\Storage;

/**
 * The uploaded file is stored in tmp/, converted, and removed in a finally
 * block regardless of outcome. Only the resulting markdown persists.
 */
final class FileStorage
{
    private const HASH_PATTERN = '/^[a-f0-9]{15}$/';

    public function __construct(
        private readonly string $mdDir,
        private readonly string $tmpDir,
    ) {
        foreach ([$mdDir, $tmpDir] as $dir) {
            if (!is_dir($dir) && !mkdir($dir, 0750, true) && !is_dir($dir)) {
                throw new \RuntimeException("Cannot create directory: $dir");
            }
        }
    }

    public function reserveOriginalPath(string $hash): string
    {
        return $this->tmpDir . '/' . $this->assertHash($hash) . '.original';
    }

    /**
     * @param bool $isHttpUpload false only in tests, where there is no real
     *                           multipart upload for move_uploaded_file to verify
     */
    public function acceptUpload(string $uploadedTmpPath, string $hash, bool $isHttpUpload = true): string
    {
        $destination = $this->reserveOriginalPath($hash);

        $moved = $isHttpUpload
            ? move_uploaded_file($uploadedTmpPath, $destination)
            : rename($uploadedTmpPath, $destination);

        if (!$moved) {
            throw new \RuntimeException('Failed to store the uploaded file.');
        }

        chmod($destination, 0640);

        return $destination;
    }

    public function deleteOriginal(string $path): void
    {
        $real   = realpath($path);
        $tmpDir = realpath($this->tmpDir);

        if ($real === false) {
            return; // already gone
        }

        if ($tmpDir === false || !str_starts_with($real, $tmpDir . DIRECTORY_SEPARATOR)) {
            throw new \InvalidArgumentException('Refusing to delete a path outside the temp directory.');
        }

        @unlink($real);
    }

    public function markdownPath(string $hash): string
    {
        return $this->mdDir . '/' . $this->assertHash($hash) . '.md';
    }

    public function markdownExists(string $hash): bool
    {
        return is_file($this->markdownPath($hash));
    }

    public function markdownSize(string $hash): int
    {
        $path = $this->markdownPath($hash);

        if (!is_file($path)) {
            return 0;
        }

        clearstatcache(true, $path);

        return (int) filesize($path);
    }

    /**
     * Read at most $bytes from the start, trimmed back to the last complete
     * UTF-8 character so the API preview is never cut mid-codepoint.
     */
    public function readMarkdownPrefix(string $hash, int $bytes): string
    {
        $path = $this->markdownPath($hash);

        if (!is_file($path) || $bytes <= 0) {
            return '';
        }

        $handle = fopen($path, 'rb');
        if ($handle === false) {
            return '';
        }

        try {
            // Read a few bytes past the target so mb_strcut can see whether the
            // byte at the cut point starts a multi-byte sequence that continues
            // beyond it; without that lookahead it has no way to distinguish a
            // genuinely short chunk from one truncated mid-character.
            $chunk = (string) fread($handle, $bytes + 3);
        } finally {
            fclose($handle);
        }

        return mb_strcut($chunk, 0, $bytes, 'UTF-8');
    }

    public function streamMarkdown(string $hash): void
    {
        $path = $this->markdownPath($hash);

        if (!is_file($path)) {
            throw new \RuntimeException("Markdown not found for hash: $hash");
        }

        readfile($path);
    }

    public function deleteMarkdown(string $hash): bool
    {
        $path = $this->markdownPath($hash);

        return is_file($path) && unlink($path);
    }

    /**
     * @param list<string> $knownHashes
     * @return list<string> hashes with a .md file but no record
     */
    public function orphanMarkdownHashes(array $knownHashes): array
    {
        $known   = array_flip($knownHashes);
        $orphans = [];

        foreach (glob($this->mdDir . '/*.md') ?: [] as $file) {
            $hash = basename($file, '.md');

            if (preg_match(self::HASH_PATTERN, $hash) === 1 && !isset($known[$hash])) {
                $orphans[] = $hash;
            }
        }

        sort($orphans);

        return $orphans;
    }

    /** @return list<string> absolute paths of stale files in tmp/ */
    public function staleTempFiles(int $olderThanTimestamp): array
    {
        $stale = [];

        foreach (glob($this->tmpDir . '/*') ?: [] as $file) {
            if (!is_file($file) || str_ends_with($file, '.lock')) {
                continue;
            }
            if (filemtime($file) < $olderThanTimestamp) {
                $stale[] = $file;
            }
        }

        return $stale;
    }

    private function assertHash(string $hash): string
    {
        if (preg_match(self::HASH_PATTERN, $hash) !== 1) {
            throw new \InvalidArgumentException("Invalid hash: $hash");
        }

        return $hash;
    }
}
