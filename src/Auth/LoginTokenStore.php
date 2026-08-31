<?php

declare(strict_types=1);

namespace Data2Md\Auth;

use Data2Md\Storage\Database;

/**
 * Single-use sign-in links.
 *
 * The raw token goes in the e-mail; only its SHA-256 is stored. The token is
 * 32 bytes of random_bytes, so a keyed MAC would add nothing against brute
 * force, and hex-encoded because mail clients mangle base64's +/= in a URL.
 */
final class LoginTokenStore
{
    public function __construct(
        private readonly Database $db,
        private readonly int $ttlSeconds,
    ) {
    }

    public static function hash(string $rawToken): string
    {
        return hash('sha256', $rawToken);
    }

    /** Returns the raw token to put in the link. */
    public function issue(string $email, ?int $now = null): string
    {
        $now   = $now ?? time();
        $raw   = bin2hex(random_bytes(32));

        $statement = $this->db->pdo()->prepare(
            'INSERT INTO login_tokens (token_hash, email, created_at, expires_at)
             VALUES (:hash, :email, :created, :expires)'
        );
        $statement->execute([
            'hash'    => self::hash($raw),
            'email'   => UserStore::normalizeEmail($email),
            'created' => $now,
            'expires' => $now + $this->ttlSeconds,
        ]);

        return $raw;
    }

    /**
     * Marks the token used and returns the address it was issued for, or null.
     *
     * One autocommit UPDATE, so winning the race and reading the row cannot be
     * separated: two requests consuming the same token means exactly one gets
     * a row count of 1. Null covers unknown, already used and expired alike,
     * and callers must not distinguish them to the visitor.
     */
    public function consume(string $rawToken, ?int $now = null): ?string
    {
        $now  = $now ?? time();
        $hash = self::hash($rawToken);

        $update = $this->db->pdo()->prepare(
            'UPDATE login_tokens SET used_at = :now
              WHERE token_hash = :hash AND used_at IS NULL AND expires_at > :now'
        );
        $update->execute(['now' => $now, 'hash' => $hash]);

        if ($update->rowCount() !== 1) {
            return null;
        }

        $select = $this->db->pdo()->prepare('SELECT email FROM login_tokens WHERE token_hash = :hash');
        $select->execute(['hash' => $hash]);

        $email = $select->fetchColumn();

        return $email === false ? null : (string) $email;
    }

    /** The address a token was issued for, without consuming it. */
    public function peekEmail(string $rawToken, ?int $now = null): ?string
    {
        $now = $now ?? time();

        $statement = $this->db->pdo()->prepare(
            'SELECT email FROM login_tokens
              WHERE token_hash = :hash AND used_at IS NULL AND expires_at > :now'
        );
        $statement->execute(['hash' => self::hash($rawToken), 'now' => $now]);

        $email = $statement->fetchColumn();

        return $email === false ? null : (string) $email;
    }

    /** Shortens how long several requested links stay live at once. */
    public function invalidateOthersFor(string $email, string $keepHash, ?int $now = null): int
    {
        $statement = $this->db->pdo()->prepare(
            'UPDATE login_tokens SET used_at = :now
              WHERE email = :email AND token_hash != :keep AND used_at IS NULL'
        );
        $statement->execute([
            'now'   => $now ?? time(),
            'email' => UserStore::normalizeEmail($email),
            'keep'  => $keepHash,
        ]);

        return $statement->rowCount();
    }

    /**
     * Purges on expiry, not on use: the row is the only record that a token was
     * spent, so removing a used one while its raw value is still inside the TTL
     * would let it be replayed.
     */
    public function purgeExpired(?int $now = null): int
    {
        $statement = $this->db->pdo()->prepare('DELETE FROM login_tokens WHERE expires_at <= :now');
        $statement->execute(['now' => $now ?? time()]);

        return $statement->rowCount();
    }

    public function count(): int
    {
        return (int) $this->db->pdo()->query('SELECT COUNT(*) FROM login_tokens')->fetchColumn();
    }
}
