<?php

declare(strict_types=1);

namespace Data2Md\Storage;

class MappingStorage
{
    private string $mappingFile;
    private string $lockFile;

    public function __construct(string $mappingDir)
    {
        if (!is_dir($mappingDir) && !mkdir($mappingDir, 0750, true)) {
            throw new \RuntimeException("Cannot create mappings directory: $mappingDir");
        }
        $this->mappingFile = $mappingDir . '/mappings.json';
        $this->lockFile    = $mappingDir . '/.mappings.lock';
    }

    public function save(string $hash, string $originalName, string $format): void
    {
        $lock = fopen($this->lockFile, 'c');
        flock($lock, LOCK_EX);
        try {
            $mappings        = $this->loadAll();
            $mappings[$hash] = [
                'original_name' => $originalName,
                'format'        => $format,
                'created_at'    => time(),
            ];
            file_put_contents(
                $this->mappingFile,
                json_encode($mappings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
            );
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    public function find(string $hash): ?array
    {
        return $this->loadAll()[$hash] ?? null;
    }

    private function loadAll(): array
    {
        if (!file_exists($this->mappingFile)) {
            return [];
        }
        $content = file_get_contents($this->mappingFile);
        return json_decode($content ?: '{}', true) ?? [];
    }
}
