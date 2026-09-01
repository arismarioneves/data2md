<?php

declare(strict_types=1);

namespace Data2Md\Convert;

/**
 * What a conversion attempt produced, as data.
 *
 * The pipeline returns one of these instead of writing a response, so its
 * cleanup runs before anything reaches the wire: ApiResponse exits, and exit
 * does not run an enclosing finally.
 */
final class ConversionOutcome
{
    /**
     * @param array<string,mixed> $payload
     * @param list<string> $errors
     * @param array<string,string> $headers
     */
    private function __construct(
        public readonly int $status,
        public readonly array $payload,
        public readonly ?string $message,
        public readonly array $errors,
        public readonly array $headers,
    ) {
    }

    /** @param array<string,mixed> $payload */
    public static function success(array $payload, int $status = 201): self
    {
        return new self($status, $payload, null, [], []);
    }

    /**
     * @param list<string> $errors
     * @param array<string,string> $headers
     */
    public static function failure(
        string $message,
        int $status,
        array $errors = [],
        array $headers = [],
    ): self {
        return new self($status, [], $message, $errors, $headers);
    }

    public function isSuccess(): bool
    {
        return $this->message === null;
    }
}
