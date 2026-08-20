<?php

declare(strict_types=1);

namespace Data2Md\Config;

/**
 * Every key is required. Nothing here invents a value: production defaults are
 * declared as ENV in the Dockerfile, which is the single source of truth for
 * them. A missing or invalid key aborts the boot with every problem listed at
 * once, so whoever is configuring the panel sees the full picture in one pass.
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
            if (!array_key_exists($key, $raw)) {
                $problems[] = "$key: missing (required — no default in code)";
                continue;
            }

            $input = trim((string) $raw[$key]);

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

        if ($problems !== []) {
            throw new ConfigError($problems);
        }

        return new self($values);
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

    /** @return list<string> */
    public function list(string $key): array
    {
        $value = $this->raw($key);
        return is_array($value) ? $value : [];
    }
}
