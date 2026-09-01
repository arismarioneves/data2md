<?php

declare(strict_types=1);

namespace Data2Md\Auth;

use Data2Md\Storage\Database;

/**
 * One API credential per account.
 *
 * The raw token is returned once, by the call that creates it, and only its
 * SHA-256 is stored — so a leaked database cannot be used to call the API.
 * UNIQUE on user_id makes rotation a replacement rather than an accumulation,
 * which means "revoke the old one" is not a separate step someone can forget.
 *
 * There is no active flag: a suspended account is suspended at the account, and
 * a second source of truth for "may this token work" would only be somewhere
 * for the two to disagree.
 */
final class ApiTokenStore
{
    /** Identifies the credential in a paste, a log or a secret scanner. */
    public const PREFIX = 'd2md_';

    private const SECRET_BYTES = 24;

    /** last_used_at is only written past this age, to keep reads read-only. */
    private const TOUCH_AFTER_SECONDS = 3600;

    public function __construct(private readonly Database $db)
    {
    }

    public static function hash(string $rawToken): string
    {
        return hash('sha256', $rawToken);
    }

    /** Shape check only, so an obviously wrong credential costs no query. */
    public static function hasTokenShape(string $candidate): bool
    {
        return preg_match('/^' . self::PREFIX . '[a-f0-9]{' . (self::SECRET_BYTES * 2) . '}$/', $candidate) === 1;
    }

    /**
     * Issues the account's token, replacing any it already had.
     *
     * @return string the raw token; it cannot be recovered afterwards
     */
    public function issue(int $userId, ?int $now = null): string
    {
        $now = $now ?? time();
        $raw = self::PREFIX . bin2hex(random_bytes(self::SECRET_BYTES));

        $statement = $this->db->pdo()->prepare(
            'INSERT INTO api_tokens (user_id, token_hash, prefix, last4, created_at)
                  VALUES (:user, :hash, :prefix, :last4, :created)
             ON CONFLICT(user_id) DO UPDATE SET
                    token_hash   = excluded.token_hash,
                    prefix       = excluded.prefix,
                    last4        = excluded.last4,
                    rotated_at   = :rotated,
                    last_used_at = NULL'
        );
        $statement->execute([
            'user'    => $userId,
            'hash'    => self::hash($raw),
            'prefix'  => self::maskPrefix($raw),
            'last4'   => substr($raw, -4),
            'created' => $now,
            'rotated' => $now,
        ]);

        return $raw;
    }

    /**
     * The account a token belongs to, or null when the token is unknown.
     *
     * Writes last_used_at past the threshold, decided here rather than in a
     * conditional UPDATE: a statement that matches no rows still takes the
     * write lock, and this runs on every API request.
     *
     * @return array<string,mixed>|null the users row
     */
    public function authenticate(string $rawToken, ?int $now = null): ?array
    {
        if (!self::hasTokenShape($rawToken)) {
            return null;
        }

        $now  = $now ?? time();
        $hash = self::hash($rawToken);

        $statement = $this->db->pdo()->prepare(
            'SELECT u.*, t.last_used_at AS token_last_used_at
               FROM api_tokens t
               JOIN users u ON u.id = t.user_id
              WHERE t.token_hash = :hash'
        );
        $statement->execute(['hash' => $hash]);

        $row = $statement->fetch();

        if ($row === false) {
            return null;
        }

        $lastUsed = $row['token_last_used_at'];

        if ($lastUsed === null || ($now - (int) $lastUsed) >= self::TOUCH_AFTER_SECONDS) {
            $this->touch($hash, $now);
        }

        unset($row['token_last_used_at']);

        return $row;
    }

    /**
     * What the dashboard may show about a token: enough to recognise it, never
     * enough to use it.
     *
     * @return array<string,mixed>|null
     */
    public function describeFor(int $userId): ?array
    {
        $statement = $this->db->pdo()->prepare(
            'SELECT prefix, last4, created_at, rotated_at, last_used_at
               FROM api_tokens WHERE user_id = :user'
        );
        $statement->execute(['user' => $userId]);

        $row = $statement->fetch();

        return $row === false ? null : $row;
    }

    public function revokeFor(int $userId): bool
    {
        $statement = $this->db->pdo()->prepare('DELETE FROM api_tokens WHERE user_id = :user');
        $statement->execute(['user' => $userId]);

        return $statement->rowCount() === 1;
    }

    public function count(): int
    {
        return (int) $this->db->pdo()->query('SELECT COUNT(*) FROM api_tokens')->fetchColumn();
    }

    /** The leading characters shown beside last4, e.g. d2md_a1b2. */
    private static function maskPrefix(string $rawToken): string
    {
        return substr($rawToken, 0, strlen(self::PREFIX) + 4);
    }

    private function touch(string $tokenHash, int $now): void
    {
        $statement = $this->db->pdo()->prepare(
            'UPDATE api_tokens SET last_used_at = :now WHERE token_hash = :hash'
        );
        $statement->execute(['now' => $now, 'hash' => $tokenHash]);
    }
}
