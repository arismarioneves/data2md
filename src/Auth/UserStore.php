<?php

declare(strict_types=1);

namespace Data2Md\Auth;

use Data2Md\Storage\Database;

/**
 * Accounts, keyed by e-mail.
 *
 * Sign-in is also sign-up: consuming a valid link for an unknown address
 * creates the account.
 */
final class UserStore
{
    public function __construct(private readonly Database $db)
    {
    }

    /**
     * Canonical form for storage and lookup. SQLite's NOCASE collation only
     * folds ASCII, so the stored value is normalised here as well.
     *
     * Gmail dots and +tags are deliberately left alone: they address different
     * inboxes as far as this app is concerned.
     */
    public static function normalizeEmail(string $email): string
    {
        return mb_strtolower(trim($email), 'UTF-8');
    }

    /** @return array<string,mixed>|null */
    public function findByEmail(string $email): ?array
    {
        return $this->one(
            'SELECT * FROM users WHERE email = :email',
            ['email' => self::normalizeEmail($email)]
        );
    }

    /** @return array<string,mixed>|null */
    public function findById(int $id): ?array
    {
        return $this->one('SELECT * FROM users WHERE id = :id', ['id' => $id]);
    }

    /** Returns the account id, creating the account if the address is new. */
    public function ensure(string $email): int
    {
        $email = self::normalizeEmail($email);
        $now   = time();

        // INSERT OR IGNORE rather than a read followed by a write: two links
        // consumed at once would otherwise both see no row and both insert.
        $statement = $this->db->pdo()->prepare(
            'INSERT OR IGNORE INTO users (email, created_at) VALUES (:email, :now)'
        );
        $statement->execute(['email' => $email, 'now' => $now]);

        $row = $this->one('SELECT id FROM users WHERE email = :email', ['email' => $email]);

        if ($row === null) {
            throw new \RuntimeException('Failed to create the account.');
        }

        return (int) $row['id'];
    }

    public function touchLastLogin(int $id, ?int $at = null): void
    {
        $statement = $this->db->pdo()->prepare(
            'UPDATE users SET last_login_at = :at WHERE id = :id'
        );
        $statement->execute(['at' => $at ?? time(), 'id' => $id]);
    }

    /**
     * Grants premium outright, ahead of any subscription, or clears the grant
     * with null. Entitlement reads it first, so this is what a courtesy account
     * is made of.
     */
    public function setPremiumUntil(int $id, ?int $until): void
    {
        $statement = $this->db->pdo()->prepare(
            'UPDATE users SET premium_until = :until WHERE id = :id'
        );
        $statement->execute(['until' => $until, 'id' => $id]);
    }

    /**
     * Sessions and API tokens cascade. Conversions detach rather than vanish:
     * their markdown stays reachable by hash, as it is for anonymous ones.
     */
    public function delete(int $id): bool
    {
        $statement = $this->db->pdo()->prepare('DELETE FROM users WHERE id = :id');
        $statement->execute(['id' => $id]);

        return $statement->rowCount() === 1;
    }

    public function count(): int
    {
        return (int) $this->db->pdo()->query('SELECT COUNT(*) FROM users')->fetchColumn();
    }

    /**
     * Most recent first.
     *
     * @return list<array<string,mixed>>
     */
    public function recent(int $limit): array
    {
        $statement = $this->db->pdo()->prepare(
            'SELECT * FROM users ORDER BY created_at DESC, id DESC LIMIT :limit'
        );
        $statement->bindValue('limit', max(1, $limit), \PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAll();
    }

    /**
     * @param array<string,mixed> $params
     * @return array<string,mixed>|null
     */
    private function one(string $sql, array $params): ?array
    {
        $statement = $this->db->pdo()->prepare($sql);
        $statement->execute($params);

        $row = $statement->fetch();

        return $row === false ? null : $row;
    }
}
