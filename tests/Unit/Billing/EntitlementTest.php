<?php

declare(strict_types=1);

namespace Data2Md\Tests\Unit\Billing;

use Data2Md\Billing\Entitlement;
use PHPUnit\Framework\TestCase;

final class EntitlementTest extends TestCase
{
    private const NOW = 1_800_000_000;

    public function testNoAccountIsAnonymous(): void
    {
        self::assertSame(Entitlement::ANON, Entitlement::forUser(null, self::NOW));
        self::assertFalse(Entitlement::isPremium(null, self::NOW));
    }

    /**
     * @dataProvider subscriptionStates
     * @param array<string,mixed> $columns
     */
    public function testPlanFollowsTheSubscription(array $columns, string $expected, string $why): void
    {
        self::assertSame($expected, Entitlement::forUser($columns, self::NOW), $why);
    }

    public static function subscriptionStates(): array
    {
        return [
            'never subscribed' => [
                ['subscription_status' => null, 'premium_until' => null],
                Entitlement::FREE,
                'an account with no subscription',
            ],
            'active' => [
                ['subscription_status' => 'active', 'premium_until' => null],
                Entitlement::PREMIUM,
                'paying',
            ],
            'trialing' => [
                ['subscription_status' => 'trialing', 'premium_until' => null],
                Entitlement::PREMIUM,
                'a trial is entitled',
            ],
            // The most common accidental "I paid and you cut me off".
            'cancelling at period end' => [
                ['subscription_status' => 'active', 'cancel_at_period_end' => 1, 'premium_until' => null],
                Entitlement::PREMIUM,
                'the period is paid for',
            ],
            'past due' => [
                ['subscription_status' => 'past_due', 'premium_until' => null],
                Entitlement::FREE,
                'payment failed',
            ],
            'canceled' => [
                ['subscription_status' => 'canceled', 'premium_until' => null],
                Entitlement::FREE,
                'gone',
            ],
            'incomplete' => [
                ['subscription_status' => 'incomplete', 'premium_until' => null],
                Entitlement::FREE,
                'never completed',
            ],
            'unpaid' => [
                ['subscription_status' => 'unpaid', 'premium_until' => null],
                Entitlement::FREE,
                'unpaid',
            ],
            'paused' => [
                ['subscription_status' => 'paused', 'premium_until' => null],
                Entitlement::FREE,
                'paused',
            ],
            // An allowlist, so a status Stripe invents later cannot grant access
            // by accident.
            'unknown future status' => [
                ['subscription_status' => 'something_new', 'premium_until' => null],
                Entitlement::FREE,
                'not on the allowlist',
            ],
        ];
    }

    public function testManualOverrideGrantsPremiumWithoutStripe(): void
    {
        $comped = ['subscription_status' => null, 'premium_until' => self::NOW + 86400];

        self::assertSame(Entitlement::PREMIUM, Entitlement::forUser($comped, self::NOW));
    }

    public function testExpiredManualOverrideDoesNotGrantPremium(): void
    {
        $lapsed = ['subscription_status' => null, 'premium_until' => self::NOW - 1];

        self::assertSame(Entitlement::FREE, Entitlement::forUser($lapsed, self::NOW));
    }

    public function testManualOverrideWinsOverALapsedSubscription(): void
    {
        $both = ['subscription_status' => 'canceled', 'premium_until' => self::NOW + 86400];

        self::assertSame(Entitlement::PREMIUM, Entitlement::forUser($both, self::NOW));
    }
}
