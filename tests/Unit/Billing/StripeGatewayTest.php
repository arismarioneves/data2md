<?php

declare(strict_types=1);

namespace Data2Md\Tests\Unit\Billing;

use Data2Md\Billing\StripeGateway;
use PHPUnit\Framework\TestCase;
use Stripe\Exception\SignatureVerificationException;

final class StripeGatewayTest extends TestCase
{
    private const SECRET = 'whsec_test_secret';

    private function signed(string $payload, ?int $timestamp = null): string
    {
        $timestamp ??= time();

        return sprintf(
            't=%d,v1=%s',
            $timestamp,
            hash_hmac('sha256', "$timestamp.$payload", self::SECRET)
        );
    }

    /**
     * The failure this test exists for: the SDK refuses to construct a client
     * without an API key, and building one eagerly made every request to the
     * webhook a fatal error on a deployment with billing switched off. That
     * endpoint answers whatever the flags say, precisely so a paid subscription
     * is never silently unrecorded, so a crash there is the worst place for it.
     */
    public function testItConstructsWithoutAnApiKey(): void
    {
        $gateway = new StripeGateway('', '2026-08-26.dahlia', self::SECRET);

        self::assertFalse($gateway->hasSecretKey());
        self::assertTrue($gateway->hasWebhookSecret());
    }

    /** Verifying a signature is an HMAC over the body; no API key is involved. */
    public function testItVerifiesASignatureWithoutAnApiKey(): void
    {
        $gateway = new StripeGateway('', '2026-08-26.dahlia', self::SECRET);
        $payload = '{"id":"evt_1","type":"customer.subscription.updated"}';

        $event = $gateway->verifyWebhook($payload, $this->signed($payload));

        self::assertSame('evt_1', $event['id']);
        self::assertSame('customer.subscription.updated', $event['type']);
    }

    public function testAForgedBodyIsRejectedRatherThanCrashing(): void
    {
        $gateway = new StripeGateway('', '2026-08-26.dahlia', self::SECRET);
        $payload = '{"id":"evt_forged"}';

        $this->expectException(SignatureVerificationException::class);

        $gateway->verifyWebhook($payload, $this->signed('{"id":"evt_1"}'));
    }

    public function testAnUnsignedBodyIsRejectedRatherThanCrashing(): void
    {
        $gateway = new StripeGateway('', '2026-08-26.dahlia', self::SECRET);

        $this->expectException(SignatureVerificationException::class);

        $gateway->verifyWebhook('{"id":"evt_1"}', '');
    }

    /**
     * Stripe's tolerance is five minutes, and a container whose clock has
     * drifted past it fails every delivery while nothing else looks wrong.
     */
    public function testAnOldTimestampIsRejected(): void
    {
        $gateway = new StripeGateway('', '2026-08-26.dahlia', self::SECRET);
        $payload = '{"id":"evt_1"}';

        $this->expectException(SignatureVerificationException::class);

        $gateway->verifyWebhook($payload, $this->signed($payload, time() - 3_600));
    }

    public function testLiveModeIsRecognisedFromTheKey(): void
    {
        self::assertTrue((new StripeGateway('sk_live_x', 'v'))->isLiveMode());
        self::assertFalse((new StripeGateway('sk_test_x', 'v'))->isLiveMode());
        self::assertFalse((new StripeGateway('', 'v'))->isLiveMode());
    }
}
