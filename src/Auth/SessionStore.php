<?php

declare(strict_types=1);

namespace Data2Md\Auth;

use Data2Md\Storage\Database;

/**
 * Sessions in the database rather than PHP's own.
 *
 * session_start() holds an exclusive per-session file lock for the whole
 * request, which would serialise a user's minutes-long upload against any
 * other navigation they make. Debian's PHP also ships session.gc_probability=0
 * and delegates cleanup to a cron job this container does not have. A table
 * additionally gives server-side revocation and one JOIN to the account.
 *
 * The cookie carries 32 bytes of random_bytes; only its SHA-256 is stored.
 */
final class SessionStore
{
    public const COOKIE = 'd2md_sess';

    /** last_seen_at is only written past this age, to keep reads read-only. */
    private const TOUCH_AFTER_SECONDS = 3600;

    public function __construct(
        private readonly Database $db,
        private readonly int $lifetimeDays,
        private readonly int $idleDays,
    ) {
    }

    public static function hash(string $rawToken): string
    {
        return hash('sha256', $rawToken);
    }

    /** Returns the raw token for the cookie. */
    public function create(int $userId, ?int $now = null): string
    {
        $now = $now ?? time();
        $raw = bin2hex(random_bytes(32));

        $statement = $this->db->pdo()->prepare(
            'INSERT INTO sessions (token_hash, user_id, csrf_token, created_at, last_seen_at, expires_at)
             VALUES (:hash, :user, :csrf, :now, :now, :expires)'
        );
        $statement->execute([
            'hash'    => self::hash($raw),
            'user'    => $userId,
            'csrf'    => bin2hex(random_bytes(32)),
            'now'     => $now,
            'expires' => $now + ($this->lifetimeDays * 86400),
        ]);

        return $raw;
    }

    /**
     * The session and its account in one query, or null when the token is
     * unknown, past its absolute lifetime, or idle for too long.
     *
     * @return array<string,mixed>|null
     */
    public function find(string $rawToken, ?int $now = null): ?array
    {
        $now = $now ?? time();

        $statement = $this->db->pdo()->prepare(
            'SELECT s.token_hash, s.csrf_token, s.created_at, s.last_seen_at, s.expires_at,
                    u.*
               FROM sessions s
               JOIN users u ON u.id = s.user_id
              WHERE s.token_hash = :hash
                AND s.expires_at > :now
                AND s.last_seen_at > :idleCut'
        );
        $statement->execute([
            'hash'    => self::hash($rawToken),
            'now'     => $now,
            'idleCut' => $now - ($this->idleDays * 86400),
        ]);

        $row = $statement->fetch();

        if ($row === false) {
            return null;
        }

        // Decided here rather than in a conditional UPDATE: a statement that
        // matches no rows still takes the write lock.
        if (($now - (int) $row['last_seen_at']) >= self::TOUCH_AFTER_SECONDS) {
            $this->touch((string) $row['token_hash'], $now);
            $row['last_seen_at'] = $now;
        }

        return $row;
    }

    private function touch(string $tokenHash, int $now): void
    {
        $statement = $this->db->pdo()->prepare(
            'UPDATE sessions SET last_seen_at = :now WHERE token_hash = :hash'
        );
        $statement->execute(['now' => $now, 'hash' => $tokenHash]);
    }

    public function destroy(string $rawToken): void
    {
        $statement = $this->db->pdo()->prepare('DELETE FROM sessions WHERE token_hash = :hash');
        $statement->execute(['hash' => self::hash($rawToken)]);
    }

    public function destroyAllFor(int $userId): int
    {
        $statement = $this->db->pdo()->prepare('DELETE FROM sessions WHERE user_id = :user');
        $statement->execute(['user' => $userId]);

        return $statement->rowCount();
    }

    /** Covers both the absolute lifetime and the idle cut. */
    public function purgeExpired(?int $now = null): int
    {
        $now = $now ?? time();

        $statement = $this->db->pdo()->prepare(
            'DELETE FROM sessions WHERE expires_at <= :now OR last_seen_at <= :idleCut'
        );
        $statement->execute([
            'now'     => $now,
            'idleCut' => $now - ($this->idleDays * 86400),
        ]);

        return $statement->rowCount();
    }

    public function count(): int
    {
        return (int) $this->db->pdo()->query('SELECT COUNT(*) FROM sessions')->fetchColumn();
    }
}
