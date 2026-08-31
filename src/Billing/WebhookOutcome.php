<?php

declare(strict_types=1);

namespace Data2Md\Billing;

/**
 * What to answer Stripe with.
 *
 * Stripe reads only the status: 2xx means done, anything else means deliver it
 * again. The body is for a human reading `stripe listen` output.
 */
final class WebhookOutcome
{
    /** @param array<string,string> $headers */
    public function __construct(
        public readonly int $status,
        public readonly string $body,
        public readonly array $headers = [],
    ) {
    }

    public static function ok(string $body): self
    {
        return new self(200, $body);
    }

    /** Temporary: Stripe should come back rather than give up. */
    public static function retry(string $body, int $after): self
    {
        return new self(503, $body, ['Retry-After' => (string) $after]);
    }

    public static function failed(string $body): self
    {
        return new self(500, $body);
    }
}
