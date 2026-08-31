<?php

declare(strict_types=1);

namespace Data2Md\Tests\Unit\Billing;

use Data2Md\Config\Config;
use PHPUnit\Framework\TestCase;
use Stripe\Util\ApiVersion;

final class StripeApiVersionTest extends TestCase
{
    /**
     * The SDK deserialises the shapes of the version it was built for, and this
     * app sends the pinned version on every call. If composer moves the SDK and
     * the pin stays behind, the two disagree about payloads -- silently, because
     * a field that moved reads as null rather than raising anything.
     *
     * Failing here is the intended outcome of an SDK bump: read the Stripe API
     * changelog between the two versions, check what this app reads off a
     * Subscription and a Checkout Session, then move the pin.
     */
    public function testPinnedVersionMatchesTheInstalledSdk(): void
    {
        self::assertSame(
            ApiVersion::CURRENT,
            Config::defaultFor('STRIPE_API_VERSION'),
            'the pinned STRIPE_API_VERSION default no longer matches stripe/stripe-php'
        );
    }
}
