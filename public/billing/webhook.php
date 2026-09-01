<?php

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__, 2));

/** @var \Data2Md\Config\Config $config */
$config = require BASE_PATH . '/src/bootstrap.php';

use Data2Md\Auth\UserStore;
use Data2Md\Billing\BillingStore;
use Data2Md\Billing\EntitlementWriter;
use Data2Md\Billing\StripeGateway;
use Data2Md\Billing\WebhookEventStore;
use Data2Md\Billing\WebhookHandler;
use Data2Md\Log\Logger;
use Data2Md\Storage\Database;
use Stripe\Exception\SignatureVerificationException;

/**
 * Stripe's delivery endpoint.
 *
 * Deliberately outside everything the rest of the app runs through:
 *
 *   no rate limiter — Stripe delivers from a handful of egress addresses, which
 *     would share one bucket, take a 429, and get the endpoint disabled after
 *     repeated failures
 *   no CORS, no CSRF, no session — the signature is the authentication
 *   no i18n — nothing here is read by a person in a browser
 *   no garbage-collection hook — a sweep must not delay an acknowledgement
 *
 * It also answers with ACCOUNTS_ENABLED off. Turning accounts off must not make
 * a paid subscription silently stop being recorded.
 */
header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-store');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo "Method not allowed. Stripe posts here.\n";
    exit;
}

$dataDir = $config->string('APP_DATA_DIR');
$logger  = new Logger($dataDir . '/logs');
$gateway = StripeGateway::fromConfig($config);

// Both are needed: the secret to verify the delivery, the key to read the
// subscription back. 503 rather than 400, because nothing about the delivery
// is wrong -- Stripe should keep the event and try again once the deployment
// is fixed, rather than give up on a paid subscription.
if (!$gateway->hasWebhookSecret() || !$gateway->hasSecretKey()) {
    $logger->app('billing.webhook.unconfigured', [
        'webhook_secret' => $gateway->hasWebhookSecret(),
        'secret_key'     => $gateway->hasSecretKey(),
    ], 'error');

    http_response_code(503);
    echo "Stripe is not configured on this deployment.\n";
    exit;
}

$payload   = (string) file_get_contents('php://input');
$signature = (string) ($_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '');

try {
    $event = $gateway->verifyWebhook($payload, $signature);
} catch (SignatureVerificationException $e) {
    // Also what a wrong container clock looks like: the signature carries a
    // timestamp and Stripe's tolerance is five minutes.
    $logger->app('billing.webhook.signature_fail', [
        'detail' => $e->getMessage(),
        'bytes'  => strlen($payload),
    ], 'error');

    http_response_code(400);
    echo "Signature verification failed.\n";
    exit;
} catch (\UnexpectedValueException $e) {
    $logger->app('billing.webhook.malformed', ['detail' => $e->getMessage()], 'error');

    http_response_code(400);
    echo "Malformed payload.\n";
    exit;
}

$database = new Database($dataDir . '/db/mappings.sqlite');

$outcome = (new WebhookHandler(
    new WebhookEventStore($database),
    $gateway,
    new UserStore($database),
    new EntitlementWriter(
        new BillingStore($database),
        $logger,
        $config->string('STRIPE_PRICE_ID'),
    ),
    $logger,
))->handle($event);

foreach ($outcome->headers as $name => $value) {
    header("$name: $value");
}

http_response_code($outcome->status);
echo $outcome->body;
