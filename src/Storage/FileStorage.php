<?php

declare(strict_types=1);

namespace Data2Md\Storage;

class FileStorage
{
    public function __construct(
        private readonly string $dataDir,
        private readonly string $mdDir,
        private readonly MappingStorage $mappingStorage
    ) {
        if (!is_dir($mdDir) && !mkdir($mdDir, 0750, true)) {
            throw new \RuntimeException("Cannot create md directory: $mdDir");
        }
    }

    public function storeUploadedFile(array $file, string $originalName, string $format): string
    {
        $hash      = $this->generateUniqueHash();
        $formatDir = $this->dataDir . '/' . $format;

        if (!is_dir($formatDir) && !mkdir($formatDir, 0750, true)) {
            throw new \RuntimeException("Cannot create format directory: $formatDir");
        }

        $destPath = $formatDir . '/' . $hash;

        if (!move_uploaded_file($file['tmp_name'], $destPath)) {
            throw new \RuntimeException('Failed to store uploaded file.');
        }

        // Restrict permissions so the file is not executable
        chmod($destPath, 0640);

        $this->mappingStorage->save($hash, $originalName, $format);

        return $hash;
    }

    public function storeMarkdown(string $hash, string $content): void
    {
        $mdPath = $this->mdDir . '/' . $hash . '.md';
        if (file_put_contents($mdPath, $content, LOCK_EX) === false) {
            throw new \RuntimeException('Failed to store markdown file.');
        }
        chmod($mdPath, 0640);
    }

    public function getFilePath(string $hash, string $format): ?string
    {
        $path = $this->dataDir . '/' . $format . '/' . $hash;
        return file_exists($path) ? $path : null;
    }

    public function getMarkdownContent(string $hash): ?string
    {
        $path = $this->mdDir . '/' . $hash . '.md';
        if (!file_exists($path)) {
            return null;
        }
        $content = file_get_contents($path);
        return $content !== false ? $content : null;
    }

    private function generateUniqueHash(): string
    {
        for ($i = 0; $i < 10; $i++) {
            $hash = substr(bin2hex(random_bytes(8)), 0, 15);
            if (!$this->hashExists($hash)) {
                return $hash;
            }
        }
        throw new \RuntimeException('Failed to generate unique hash after 10 attempts.');
    }

    private function hashExists(string $hash): bool
    {
        if (!is_dir($this->dataDir)) {
            return false;
        }
        foreach (glob($this->dataDir . '/*', GLOB_ONLYDIR) ?: [] as $dir) {
            if (file_exists($dir . '/' . $hash)) {
                return true;
            }
        }
        return false;
    }
}
