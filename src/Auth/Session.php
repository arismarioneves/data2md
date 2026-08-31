<?php

declare(strict_types=1);

namespace Data2Md\Auth;

use Data2Md\Config\Config;

/**
 * The session as the request sees it: cookie in, account out.
 *
 * load() does not create anything. An anonymous visitor and a crawler both
 * cost one array lookup, and no row exists until someone signs in.
 */
final class Session
{
    public const COOKIE = 'd2md_sess';

    /** @var array<string,mixed>|null */
    private ?array $user = null;

    private ?string $rawToken = null;

    /** @param \Closure(string,string,array<string,mixed>):void|null $cookieWriter */
    public function __construct(
        private readonly SessionStore $store,
        private readonly bool $secureCookie,
        private readonly int $lifetimeDays,
        private readonly ?\Closure $cookieWriter = null,
    ) {
    }

    public static function fromConfig(Config $config, SessionStore $store): self
    {
        return new self(
            store: $store,
            // From APP_URL, not $_SERVER['HTTPS']: Traefik terminates TLS in
            // front of this container, so HTTPS is never set and reading it
            // would ship the cookie without Secure in production.
            secureCookie: str_starts_with($config->string('APP_URL'), 'https://'),
            lifetimeDays: $config->int('SESSION_LIFETIME_DAYS'),
        );
    }

    /** @param array<string,mixed> $cookies */
    public function load(array $cookies): void
    {
        $raw = $cookies[self::COOKIE] ?? null;

        if (!is_string($raw) || $raw === '') {
            return;
        }

        $user = $this->store->find($raw);

        if ($user === null) {
            // Expired, revoked, or belonging to a deleted account.
            $this->clearCookie();
            return;
        }

        $this->rawToken = $raw;
        $this->user     = $user;
    }

    public function isAuthenticated(): bool
    {
        return $this->user !== null;
    }

    /** @return array<string,mixed>|null */
    public function user(): ?array
    {
        return $this->user;
    }

    public function userId(): ?int
    {
        return $this->user === null ? null : (int) $this->user['id'];
    }

    public function email(): ?string
    {
        return $this->user === null ? null : (string) $this->user['email'];
    }

    public function csrfToken(): ?string
    {
        return $this->user === null ? null : (string) $this->user['csrf_token'];
    }

    public function checkCsrf(mixed $presented): bool
    {
        $expected = $this->csrfToken();

        return $expected !== null
            && is_string($presented)
            && hash_equals($expected, $presented);
    }

    /** Replaces any session already in hand, so a fixed token cannot survive. */
    public function start(int $userId): void
    {
        if ($this->rawToken !== null) {
            $this->store->destroy($this->rawToken);
        }

        $raw = $this->store->create($userId);

        $this->rawToken = $raw;
        $this->user     = $this->store->find($raw);

        $this->writeCookie($raw, time() + ($this->lifetimeDays * 86400));
    }

    public function destroy(): void
    {
        if ($this->rawToken !== null) {
            $this->store->destroy($this->rawToken);
        }

        $this->rawToken = null;
        $this->user     = null;

        $this->clearCookie();
    }

    private function clearCookie(): void
    {
        $this->writeCookie('', time() - 3600);
    }

    private function writeCookie(string $value, int $expires): void
    {
        $writer = $this->cookieWriter
            ?? static function (string $name, string $value, array $options): void {
                setcookie($name, $value, $options);
            };

        $writer(self::COOKIE, $value, [
            'expires'  => $expires,
            'path'     => '/',
            'secure'   => $this->secureCookie,
            'httponly' => true,
            // Lax, not Strict: Strict withholds the cookie on the top-level
            // navigation back from a payment page and on the sign-in link
            // itself, so the visitor lands looking signed out.
            'samesite' => 'Lax',
        ]);
    }
}
