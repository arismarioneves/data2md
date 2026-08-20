<?php

declare(strict_types=1);

namespace Data2Md\Storage;

/**
 * Registry of hash → original file.
 */
final class MappingStorage
{
    private const HASH_LENGTH   = 15;
    private const MAX_ATTEMPTS  = 10;

    public function __construct(private readonly Database $db)
    {
    }

    public function save(
        string $hash,
        string $originalName,
        string $format,
        int $sizeBytes,
        ?int $createdAt = null,
    ): void {
        $statement = $this->db->pdo()->prepare(
            <<<'SQL'
            INSERT INTO mappings (hash, original_name, format, size_bytes, created_at)
            VALUES (:hash, :original_name, :format, :size_bytes, :created_at)
            ON CONFLICT (hash) DO UPDATE SET
                original_name = excluded.original_name,
                format        = excluded.format,
                size_bytes    = excluded.size_bytes,
                created_at    = excluded.created_at
            SQL
        );

        $statement->execute([
            'hash'          => $hash,
            'original_name' => $originalName,
            'format'        => $format,
            'size_bytes'    => $sizeBytes,
            'created_at'    => $createdAt ?? time(),
        ]);
    }

    /** @return array{original_name:string,format:string,size_bytes:int,created_at:int}|null */
    public function find(string $hash): ?array
    {
        $statement = $this->db->pdo()->prepare(
            'SELECT original_name, format, size_bytes, created_at FROM mappings WHERE hash = :hash'
        );
        $statement->execute(['hash' => $hash]);

        $row = $statement->fetch();
        if ($row === false) {
            return null;
        }

        return [
            'original_name' => (string) $row['original_name'],
            'format'        => (string) $row['format'],
            'size_bytes'    => (int) $row['size_bytes'],
            'created_at'    => (int) $row['created_at'],
        ];
    }

    public function exists(string $hash): bool
    {
        $statement = $this->db->pdo()->prepare('SELECT 1 FROM mappings WHERE hash = :hash');
        $statement->execute(['hash' => $hash]);

        return $statement->fetchColumn() !== false;
    }

    public function generateUniqueHash(): string
    {
        for ($attempt = 0; $attempt < self::MAX_ATTEMPTS; $attempt++) {
            $hash = substr(bin2hex(random_bytes(8)), 0, self::HASH_LENGTH);

            if (!$this->exists($hash)) {
                return $hash;
            }
        }

        throw new \RuntimeException('Failed to generate a unique hash after ' . self::MAX_ATTEMPTS . ' attempts.');
    }

    /** @return list<string> */
    public function hashesOlderThan(int $timestamp): array
    {
        $statement = $this->db->pdo()->prepare(
            'SELECT hash FROM mappings WHERE created_at < :cut ORDER BY created_at'
        );
        $statement->execute(['cut' => $timestamp]);

        return array_map('strval', $statement->fetchAll(\PDO::FETCH_COLUMN));
    }

    /** @return list<string> */
    public function allHashes(): array
    {
        return array_map(
            'strval',
            $this->db->pdo()->query('SELECT hash FROM mappings')->fetchAll(\PDO::FETCH_COLUMN)
        );
    }

    public function delete(string $hash): void
    {
        $statement = $this->db->pdo()->prepare('DELETE FROM mappings WHERE hash = :hash');
        $statement->execute(['hash' => $hash]);
    }
}
