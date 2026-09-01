<?php

declare(strict_types=1);

namespace Data2Md\Billing;

/**
 * A Stripe subscription reduced to what this app stores, read defensively in
 * one place.
 *
 * Fields move between API versions: current_period_end left the Subscription
 * root for items.data[0] in 2025, and a field that has moved reads as null
 * rather than raising anything. Reading both shapes here means that shape
 * changing is one edit, not a hunt through the codebase for silent nulls.
 */
final class SubscriptionSnapshot
{
    public function __construct(
        public readonly string $id,
        public readonly string $status,
        public readonly ?string $customerId,
        public readonly ?string $priceId,
        public readonly ?int $currentPeriodEnd,
        public readonly bool $cancelAtPeriodEnd,
        /** From metadata this app set at checkout, never from an e-mail. */
        public readonly ?int $userId,
    ) {
    }

    /** @param array<string,mixed> $raw a Stripe Subscription as an array */
    public static function fromArray(array $raw): self
    {
        $firstItem = $raw['items']['data'][0] ?? [];

        return new self(
            id:                (string) ($raw['id'] ?? ''),
            status:            (string) ($raw['status'] ?? ''),
            customerId:        self::idOf($raw['customer'] ?? null),
            priceId:           self::idOf($firstItem['price'] ?? null),
            // Root first for an older pinned version, then the item, which is
            // where it lives from 2025 on.
            currentPeriodEnd:  self::intOrNull(
                $raw['current_period_end'] ?? ($firstItem['current_period_end'] ?? null)
            ),
            cancelAtPeriodEnd: (bool) ($raw['cancel_at_period_end'] ?? false),
            userId:            self::intOrNull($raw['metadata']['user_id'] ?? null),
        );
    }

    /** True only for a status that entitles; anything new Stripe invents is not. */
    public function isEntitling(): bool
    {
        return Entitlement::isEntitlingStatus($this->status);
    }

    /**
     * A Stripe field is either an id or the expanded object, depending on what
     * the request asked to expand.
     */
    private static function idOf(mixed $value): ?string
    {
        if (is_string($value) && $value !== '') {
            return $value;
        }

        if (is_array($value) && isset($value['id']) && is_string($value['id'])) {
            return $value['id'];
        }

        return null;
    }

    private static function intOrNull(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }
}
