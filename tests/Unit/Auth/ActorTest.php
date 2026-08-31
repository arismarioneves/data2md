<?php

declare(strict_types=1);

namespace Data2Md\Tests\Unit\Auth;

use Data2Md\Auth\Actor;
use Data2Md\Auth\Tier;
use Data2Md\Auth\TierResolver;
use Data2Md\Billing\Entitlement;
use PHPUnit\Framework\TestCase;

final class ActorTest extends TestCase
{
    private const IP = '203.0.113.10';

    private function tiers(int $freeIpWindow = 120): TierResolver
    {
        return TierResolver::of([
            Entitlement::ANON    => new Tier(Entitlement::ANON, 1, 3600, 10),
            Entitlement::FREE    => new Tier(Entitlement::FREE, 1, 600, 20),
            Entitlement::PREMIUM => new Tier(Entitlement::PREMIUM, 1, 30, 200),
        ], $freeIpWindow);
    }

    public function testAnonymousGetsTheAnonymousTierAndAnAddressBucket(): void
    {
        $actor = Actor::anonymous(self::IP, $this->tiers());

        self::assertFalse($actor->isAuthenticated());
        self::assertNull($actor->userId);
        self::assertSame(Entitlement::ANON, $actor->plan);
        self::assertSame(10, $actor->tier->maxFileSizeMb);
        self::assertSame(3600, $actor->tier->windowSeconds);
        self::assertFalse($actor->tier->allowsApi());

        $checks = $actor->quotaChecks();

        self::assertCount(1, $checks);
        self::assertSame('convert:anon:' . self::IP, $checks[0]->key);
        self::assertSame(3600, $checks[0]->windowSeconds);
    }

    public function testFreeAccountGetsAnAccountBucketAndAnAddressFloor(): void
    {
        $actor = Actor::forUser(['id' => 7, 'subscription_status' => null], self::IP, $this->tiers());

        self::assertTrue($actor->isAuthenticated());
        self::assertSame(Entitlement::FREE, $actor->plan);
        self::assertSame(20, $actor->tier->maxFileSizeMb);
        self::assertFalse($actor->tier->allowsApi());

        $keys = array_map(static fn ($c) => $c->key, $actor->quotaChecks());

        self::assertSame(['convert:user:7', 'convert:free-ip:' . self::IP], $keys);
        self::assertSame(600, $actor->quotaChecks()[0]->windowSeconds);
        self::assertSame(120, $actor->quotaChecks()[1]->windowSeconds);
    }

    /** Accounts are free and self-serve; without a floor, more of them is more quota. */
    public function testTheAddressFloorCanBeSwitchedOff(): void
    {
        $actor = Actor::forUser(['id' => 7, 'subscription_status' => null], self::IP, $this->tiers(0));

        self::assertCount(1, $actor->quotaChecks());
        self::assertSame('convert:user:7', $actor->quotaChecks()[0]->key);
    }

    /** A subscription is the attribution, so no address floor applies. */
    public function testPremiumAccountHasNoAddressFloor(): void
    {
        $actor = Actor::forUser(['id' => 7, 'subscription_status' => 'active'], self::IP, $this->tiers());

        self::assertSame(Entitlement::PREMIUM, $actor->plan);
        self::assertSame(200, $actor->tier->maxFileSizeMb);
        self::assertTrue($actor->tier->allowsApi());
        self::assertCount(1, $actor->quotaChecks());
        self::assertSame('convert:user:7', $actor->quotaChecks()[0]->key);
        self::assertSame(30, $actor->quotaChecks()[0]->windowSeconds);
    }

    /**
     * Sharing one bucket between identity kinds would let a signed-in visitor
     * inherit whatever an earlier anonymous one spent from the same address.
     */
    public function testBucketsAreNamespacedByIdentityKind(): void
    {
        $anon    = Actor::anonymous(self::IP, $this->tiers())->quotaChecks()[0]->key;
        $free    = Actor::forUser(['id' => 7], self::IP, $this->tiers(0))->quotaChecks()[0]->key;
        $premium = Actor::forUser(['id' => 7, 'subscription_status' => 'active'], self::IP, $this->tiers())
            ->quotaChecks()[0]->key;

        self::assertNotSame($anon, $free);
        self::assertSame($free, $premium, 'the same account keeps its counter across a plan change');
    }

    public function testLongestWindowCoversEveryBucket(): void
    {
        self::assertSame(3600, Actor::anonymous(self::IP, $this->tiers())->longestWindow());
        self::assertSame(600, Actor::forUser(['id' => 7], self::IP, $this->tiers())->longestWindow());
        self::assertSame(
            30,
            Actor::forUser(['id' => 7, 'subscription_status' => 'active'], self::IP, $this->tiers())->longestWindow()
        );
    }

    public function testMaxFileSizeIsExposedInBytesAndAsALabel(): void
    {
        $tier = new Tier(Entitlement::FREE, 1, 600, 20);

        self::assertSame(20 * 1024 * 1024, $tier->maxFileSizeBytes());
        self::assertSame('20 MB', $tier->maxFileSizeLabel());
    }
}
