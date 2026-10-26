<?php

declare(strict_types=1);

namespace Data2Md\Config;

/**
 * A missing or invalid key aborts boot, reporting every problem at once.
 *
 * The keys that describe the deployment itself are required. The Dockerfile
 * declares production values for most of them as ENV; APP_URL, CONTACT_EMAIL
 * and TRUSTED_PROXIES have none, because only the operator knows them. Keys
 * that describe optional behaviour carry a 'default', so an operator sets only
 * what they mean to change; an empty string is read as absent for those,
 * because control panels routinely leave a declared variable blank.
 */
final class Config
{
    private const SCHEMA = [
        'APP_ENV'                    => ['type' => 'enum', 'values' => ['production', 'development']],
        'APP_URL'                    => ['type' => 'url'],
        'APP_NAME'                   => ['type' => 'string', 'max' => 64],
        'APP_VERSION'                => ['type' => 'string', 'max' => 32],
        'APP_DATA_DIR'               => ['type' => 'path'],
        'MAX_FILE_SIZE_MB'           => ['type' => 'int', 'min' => 1, 'max' => 2048],
        'CONVERT_TIMEOUT_SECONDS'    => ['type' => 'int', 'min' => 30, 'max' => 7200],
        'CONVERT_CONCURRENCY'        => ['type' => 'int', 'min' => 1, 'max' => 16],
        'CONVERT_QUEUE_WAIT_SECONDS' => ['type' => 'int', 'min' => 0, 'max' => 600],
        'CONVERT_NICE'               => ['type' => 'int', 'min' => 0, 'max' => 19],
        'PHP_MEMORY_LIMIT_MB'        => ['type' => 'int', 'min' => 64, 'max' => 4096],
        'RATE_LIMIT_MAX'             => ['type' => 'int', 'min' => 1, 'max' => 10000],
        'RATE_LIMIT_WINDOW_SECONDS'  => ['type' => 'int', 'min' => 1, 'max' => 86400],
        'TRUSTED_PROXIES'            => ['type' => 'cidr_list', 'allow_empty' => true],
        'CORS_ALLOWED_ORIGINS'       => ['type' => 'origin_list', 'allow_empty' => true],
        'LOG_RETENTION_DAYS'         => ['type' => 'int', 'min' => 1, 'max' => 3650],
        'AUDIT_LOG_RETENTION_DAYS'   => ['type' => 'int', 'min' => 1, 'max' => 3650],
        'GC_PROBABILITY'             => ['type' => 'float', 'min' => 0.0, 'max' => 1.0],
        'CONTACT_EMAIL'              => ['type' => 'email'],

        // ── Accounts and billing, off until switched on ──────────────────────
        'ACCOUNTS_ENABLED'           => ['type' => 'bool', 'default' => 'false'],
        'BILLING_ENABLED'            => ['type' => 'bool', 'default' => 'false'],

        // ── Per-tier conversion limits ───────────────────────────────────────
        // MAX_FILE_SIZE_MB stays the ceiling Apache and PHP enforce at boot;
        // these only tighten it per caller.
        'TIER_ANON_MAX_CONVERSIONS'     => ['type' => 'int', 'min' => 1, 'max' => 10000, 'default' => '1'],
        'TIER_ANON_WINDOW_SECONDS'      => ['type' => 'int', 'min' => 1, 'max' => 86400, 'default' => '3600'],
        'TIER_ANON_MAX_FILE_SIZE_MB'    => ['type' => 'int', 'min' => 1, 'max' => 2048,  'default' => '10'],
        'TIER_FREE_MAX_CONVERSIONS'     => ['type' => 'int', 'min' => 1, 'max' => 10000, 'default' => '1'],
        'TIER_FREE_WINDOW_SECONDS'      => ['type' => 'int', 'min' => 1, 'max' => 86400, 'default' => '600'],
        'TIER_FREE_MAX_FILE_SIZE_MB'    => ['type' => 'int', 'min' => 1, 'max' => 2048,  'default' => '20'],
        // Floor applied per address on top of the per-account limit: accounts
        // are free and self-serve, so without it one machine can farm them. 0
        // disables it.
        'TIER_FREE_IP_WINDOW_SECONDS'   => ['type' => 'int', 'min' => 0, 'max' => 86400, 'default' => '120'],
        'TIER_PREMIUM_MAX_CONVERSIONS'  => ['type' => 'int', 'min' => 1, 'max' => 10000, 'default' => '1'],
        'TIER_PREMIUM_WINDOW_SECONDS'   => ['type' => 'int', 'min' => 1, 'max' => 86400, 'default' => '30'],
        'TIER_PREMIUM_MAX_FILE_SIZE_MB' => ['type' => 'int', 'min' => 1, 'max' => 2048,  'default' => '200'],

        // ── Sessions and sign-in links ───────────────────────────────────────
        'SESSION_LIFETIME_DAYS'      => ['type' => 'int', 'min' => 1, 'max' => 3650,  'default' => '30'],
        'SESSION_IDLE_DAYS'          => ['type' => 'int', 'min' => 1, 'max' => 3650,  'default' => '14'],
        'LOGIN_TOKEN_TTL_SECONDS'    => ['type' => 'int', 'min' => 60, 'max' => 86400, 'default' => '900'],
        'LOGIN_MAX_PER_EMAIL'        => ['type' => 'int', 'min' => 1, 'max' => 1000,  'default' => '3'],
        'LOGIN_MAX_PER_IP'           => ['type' => 'int', 'min' => 1, 'max' => 1000,  'default' => '10'],
        'LOGIN_WINDOW_SECONDS'       => ['type' => 'int', 'min' => 60, 'max' => 86400, 'default' => '900'],

        // ── Outbound mail ────────────────────────────────────────────────────
        'MAIL_TRANSPORT'             => ['type' => 'enum', 'values' => ['smtp', 'log'], 'default' => 'smtp'],
        'SMTP_HOST'                  => ['type' => 'string', 'max' => 255, 'allow_empty' => true, 'default' => ''],
        'SMTP_PORT'                  => ['type' => 'int', 'min' => 1, 'max' => 65535, 'default' => '587'],
        'SMTP_USER'                  => ['type' => 'string', 'max' => 255, 'allow_empty' => true, 'default' => ''],
        'SMTP_PASSWORD'              => ['type' => 'string', 'max' => 255, 'allow_empty' => true, 'default' => ''],
        'SMTP_ENCRYPTION'            => ['type' => 'enum', 'values' => ['tls', 'ssl', 'none'], 'default' => 'tls'],
        'SMTP_TIMEOUT_SECONDS'       => ['type' => 'int', 'min' => 1, 'max' => 120, 'default' => '10'],
        // Empty falls back to CONTACT_EMAIL.
        'MAIL_FROM'                  => ['type' => 'string', 'max' => 255, 'allow_empty' => true, 'default' => ''],

        // ── Stripe ───────────────────────────────────────────────────────────
        'STRIPE_SECRET_KEY'          => ['type' => 'string', 'max' => 255, 'allow_empty' => true, 'default' => ''],
        'STRIPE_WEBHOOK_SECRET'      => ['type' => 'string', 'max' => 255, 'allow_empty' => true, 'default' => ''],
        'STRIPE_PRICE_ID'            => ['type' => 'string', 'max' => 255, 'allow_empty' => true, 'default' => ''],
        // Pinned as a literal rather than read from the SDK, so upgrading the
        // SDK cannot quietly change the payload shapes this app parses. A test
        // asserts the two agree, which turns such an upgrade into a failure
        // someone has to read a changelog for.
        'STRIPE_API_VERSION'         => ['type' => 'string', 'max' => 64, 'default' => '2026-08-26.dahlia'],
        // Display mirrors of the Stripe price. Billing always uses the price
        // object; these only render the pricing page.
        'PREMIUM_PRICE_USD'          => ['type' => 'int', 'min' => 0, 'max' => 100000, 'default' => '19'],
        'PREMIUM_PRICE_BRL'          => ['type' => 'int', 'min' => 0, 'max' => 100000, 'default' => '95'],
    ];

    /** @param array<string,mixed> $values */
    private function __construct(private readonly array $values)
    {
    }

    public static function fromEnvironment(): self
    {
        $raw = [];
        foreach (array_keys(self::SCHEMA) as $key) {
            $value = getenv($key);
            if ($value !== false) {
                $raw[$key] = $value;
            }
        }
        return self::fromArray($raw);
    }

    /** @param array<string,string> $raw */
    public static function fromArray(array $raw): self
    {
        $values   = [];
        $problems = [];

        foreach (self::SCHEMA as $key => $rule) {
            $hasDefault = array_key_exists('default', $rule);
            $input      = array_key_exists($key, $raw) ? trim((string) $raw[$key]) : null;

            // A blank value is read as absent so that a key left empty in a
            // control panel falls back to its default rather than failing boot.
            if (($input === null || $input === '') && $hasDefault) {
                $input = (string) $rule['default'];
            }

            if ($input === null) {
                $problems[] = "$key: missing (required — no default in code)";
                continue;
            }

            if ($input === '' && ($rule['allow_empty'] ?? false) !== true) {
                $problems[] = "$key: must not be empty";
                continue;
            }

            try {
                $values[$key] = self::coerce($key, $input, $rule);
            } catch (\InvalidArgumentException $e) {
                $problems[] = "$key: " . $e->getMessage();
            }
        }

        if ($problems === []) {
            $problems = self::crossFieldProblems($values);
        }

        if ($problems !== []) {
            throw new ConfigError($problems);
        }

        return new self($values);
    }

    /**
     * Rules no single key can express. Only reached once every key is
     * individually valid, so the values can be read without re-checking.
     *
     * @param array<string,mixed> $v
     * @return list<string>
     */
    private static function crossFieldProblems(array $v): array
    {
        $problems = [];

        if ($v['ACCOUNTS_ENABLED'] && $v['MAIL_TRANSPORT'] === 'smtp' && $v['SMTP_HOST'] === '') {
            $problems[] = 'SMTP_HOST: required when ACCOUNTS_ENABLED=true and MAIL_TRANSPORT=smtp '
                . '(a sign-in link is the only way in)';
        }

        if ($v['BILLING_ENABLED'] && !$v['ACCOUNTS_ENABLED']) {
            $problems[] = 'BILLING_ENABLED: requires ACCOUNTS_ENABLED=true (a subscription belongs to an account)';
        }

        if ($v['BILLING_ENABLED']) {
            foreach (['STRIPE_SECRET_KEY', 'STRIPE_WEBHOOK_SECRET', 'STRIPE_PRICE_ID'] as $key) {
                if ($v[$key] === '') {
                    $problems[] = "$key: required when BILLING_ENABLED=true";
                }
            }
        }

        // A key in the wrong mode fails silently: every webhook answers 400 and
        // nothing else looks wrong.
        $secret = (string) $v['STRIPE_SECRET_KEY'];

        if ($v['APP_ENV'] === 'production' && str_starts_with($secret, 'sk_test_')) {
            $problems[] = 'STRIPE_SECRET_KEY: test-mode key with APP_ENV=production';
        }

        if ($v['APP_ENV'] === 'development' && str_starts_with($secret, 'sk_live_')) {
            $problems[] = 'STRIPE_SECRET_KEY: live key with APP_ENV=development';
        }

        if ($v['SESSION_IDLE_DAYS'] > $v['SESSION_LIFETIME_DAYS']) {
            $problems[] = sprintf(
                'SESSION_IDLE_DAYS: %d exceeds SESSION_LIFETIME_DAYS %d, so it can never expire a session',
                $v['SESSION_IDLE_DAYS'],
                $v['SESSION_LIFETIME_DAYS']
            );
        }

        // Apache's LimitRequestBody and PHP's upload_max_filesize come from
        // MAX_FILE_SIZE_MB at boot, so a tier above it is a promise the request
        // never reaches PHP to keep.
        foreach (['TIER_ANON_MAX_FILE_SIZE_MB', 'TIER_FREE_MAX_FILE_SIZE_MB', 'TIER_PREMIUM_MAX_FILE_SIZE_MB'] as $key) {
            if ($v[$key] > $v['MAX_FILE_SIZE_MB']) {
                $problems[] = sprintf(
                    '%s: %d MB exceeds MAX_FILE_SIZE_MB %d',
                    $key,
                    $v[$key],
                    $v['MAX_FILE_SIZE_MB']
                );
            }
        }

        return $problems;
    }

    /** @param array<string,mixed> $rule */
    private static function coerce(string $key, string $input, array $rule): mixed
    {
        switch ($rule['type']) {
            case 'string':
                if (mb_strlen($input) > $rule['max']) {
                    throw new \InvalidArgumentException("longer than {$rule['max']} characters");
                }
                return $input;

            case 'enum':
                if (!in_array($input, $rule['values'], true)) {
                    throw new \InvalidArgumentException(
                        'expected one of ' . implode(', ', $rule['values']) . ", got \"$input\""
                    );
                }
                return $input;

            case 'bool':
                return match (strtolower($input)) {
                    'true', '1'  => true,
                    'false', '0' => false,
                    default      => throw new \InvalidArgumentException(
                        "expected true or false, got \"$input\""
                    ),
                };

            case 'int':
                if (preg_match('/^-?\d+$/', $input) !== 1) {
                    throw new \InvalidArgumentException("expected an integer, got \"$input\"");
                }
                $int = (int) $input;
                if ($int < $rule['min'] || $int > $rule['max']) {
                    throw new \InvalidArgumentException(
                        "expected an integer between {$rule['min']} and {$rule['max']}, got $int"
                    );
                }
                return $int;

            case 'float':
                if (!is_numeric($input)) {
                    throw new \InvalidArgumentException("expected a number, got \"$input\"");
                }
                $float = (float) $input;
                if ($float < $rule['min'] || $float > $rule['max']) {
                    throw new \InvalidArgumentException(
                        "expected a number between {$rule['min']} and {$rule['max']}, got $float"
                    );
                }
                return $float;

            case 'url':
                if (filter_var($input, FILTER_VALIDATE_URL) === false) {
                    throw new \InvalidArgumentException("not a valid URL: \"$input\"");
                }
                $scheme = strtolower((string) parse_url($input, PHP_URL_SCHEME));
                if (!in_array($scheme, ['http', 'https'], true)) {
                    throw new \InvalidArgumentException("scheme must be http or https, got \"$scheme\"");
                }
                return rtrim($input, '/');

            case 'email':
                if (filter_var($input, FILTER_VALIDATE_EMAIL) === false) {
                    throw new \InvalidArgumentException("not a valid e-mail address: \"$input\"");
                }
                return $input;

            case 'path':
                if (preg_match('#^(/|[A-Za-z]:[\\\\/])#', $input) !== 1) {
                    throw new \InvalidArgumentException("must be an absolute path, got \"$input\"");
                }
                return rtrim($input, '/\\');

            case 'cidr_list':
                return self::parseList($input, static function (string $item): void {
                    if (!str_contains($item, '/')) {
                        if (filter_var($item, FILTER_VALIDATE_IP) === false) {
                            throw new \InvalidArgumentException("not a valid IP or CIDR: \"$item\"");
                        }
                        return;
                    }
                    [$addr, $bits] = explode('/', $item, 2);
                    if (filter_var($addr, FILTER_VALIDATE_IP) === false) {
                        throw new \InvalidArgumentException("not a valid IP or CIDR: \"$item\"");
                    }
                    $max = str_contains($addr, ':') ? 128 : 32;
                    if (preg_match('/^\d+$/', $bits) !== 1 || (int) $bits > $max) {
                        throw new \InvalidArgumentException("prefix out of range in \"$item\"");
                    }
                });

            case 'origin_list':
                return self::parseList($input, static function (string $item): void {
                    if (preg_match('#^https?://[A-Za-z0-9.\-]+(:\d+)?$#', $item) !== 1) {
                        throw new \InvalidArgumentException(
                            "expected scheme://host[:port], got \"$item\""
                        );
                    }
                });

            default:
                throw new \LogicException("unknown schema type for $key");
        }
    }

    /**
     * @param callable(string):void $validate
     * @return list<string>
     */
    private static function parseList(string $input, callable $validate): array
    {
        if ($input === '') {
            return [];
        }
        $items = array_values(array_filter(array_map('trim', explode(',', $input)), static fn ($i) => $i !== ''));
        foreach ($items as $item) {
            $validate($item);
        }
        return $items;
    }

    /** @return list<string> */
    public static function keys(): array
    {
        return array_keys(self::SCHEMA);
    }

    /** Keys with no default: boot fails without them. @return list<string> */
    public static function requiredKeys(): array
    {
        return array_keys(array_filter(
            self::SCHEMA,
            static fn (array $rule): bool => !array_key_exists('default', $rule)
        ));
    }

    /** @return list<string> */
    public static function optionalKeys(): array
    {
        return array_keys(array_filter(
            self::SCHEMA,
            static fn (array $rule): bool => array_key_exists('default', $rule)
        ));
    }

    /** The in-code default for an optional key, or null when the key is required. */
    public static function defaultFor(string $key): ?string
    {
        $rule = self::SCHEMA[$key] ?? null;

        return $rule === null ? null : ($rule['default'] ?? null);
    }

    private function raw(string $key): mixed
    {
        if (!array_key_exists($key, $this->values)) {
            throw new \InvalidArgumentException("unknown configuration key: $key");
        }
        return $this->values[$key];
    }

    public function string(string $key): string
    {
        return (string) $this->raw($key);
    }

    public function int(string $key): int
    {
        return (int) $this->raw($key);
    }

    public function float(string $key): float
    {
        return (float) $this->raw($key);
    }

    public function bool(string $key): bool
    {
        return (bool) $this->raw($key);
    }

    /** @return list<string> */
    public function list(string $key): array
    {
        $value = $this->raw($key);
        return is_array($value) ? $value : [];
    }
}
