<?php

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__, 2));

/** @var \Data2Md\Config\Config $config */
$config = require BASE_PATH . '/src/bootstrap.php';
require BASE_PATH . '/src/Auth/bootstrap.php';
require BASE_PATH . '/src/Billing/bootstrap.php';

use Data2Md\Billing\Entitlement;
use Data2Md\Log\Logger;
use Stripe\Exception\ApiErrorException;

$logger = new Logger($config->string('APP_DATA_DIR') . '/logs');

// POST only and CSRF-checked: starting a payment must be something the visitor
// did on purpose, not something a link did to them.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    header('Location: /pricing', true, 303);
    exit;
}

if (!$session->isAuthenticated()) {
    header('Location: /login', true, 303);
    exit;
}

if (!$session->checkCsrf($_POST['csrf'] ?? null)) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Invalid request token.\n";
    exit;
}

$user   = $session->user() ?? [];
$userId = (int) $session->userId();

// A second subscription on one account is a refund request waiting to happen,
// and the customer has no way to tell they now pay twice. The portal is where
// an existing subscription is changed.
if (Entitlement::isPremium($user)) {
    $logger->app('billing.checkout.already_subscribed', ['user' => $userId]);

    // Not straight to the portal: that endpoint is POST with a CSRF token, so
    // the dashboard is where the button to it lives.
    header('Location: /dashboard?billing=already', true, 303);
    exit;
}

$base       = rtrim(APP_URL, '/');
$customerId = is_string($user['stripe_customer_id'] ?? null) ? $user['stripe_customer_id'] : null;

try {
    // The customer is created here rather than left to Stripe to make from an
    // e-mail, and stored before the browser leaves. If every webhook for this
    // checkout is then lost, the account still has the one identifier that can
    // find the subscription it paid for.
    if ($customerId === null || $customerId === '') {
        $customerId = $stripe->createCustomer((string) $session->email(), $userId);
        $billingUsers->attachStripeCustomer($userId, $customerId);

        $logger->app('billing.customer.create', ['user' => $userId]);
    }

    $url = $stripe->createCheckoutSession(
        priceId:     $config->string('STRIPE_PRICE_ID'),
        userId:      $userId,
        customerId:  $customerId,
        email:       null,
        successUrl:  $base . '/dashboard?checkout=done',
        cancelUrl:   $base . '/pricing?checkout=cancelled',
    );
} catch (ApiErrorException $e) {
    $logger->app('billing.checkout.fail', [
        'user'   => $userId,
        'detail' => $e->getMessage(),
    ], 'error');

    header('Location: /pricing?checkout=error', true, 303);
    exit;
}

$logger->app('billing.checkout.start', ['user' => $userId]);

// A server-side redirect rather than Stripe.js: loading their script would mean
// widening the content security policy for every page that shares this header.
header('Location: ' . $url, true, 303);
