<?php

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__, 2));

/** @var \Data2Md\Config\Config $config */
$config = require BASE_PATH . '/src/bootstrap.php';
require BASE_PATH . '/src/Auth/bootstrap.php';
require BASE_PATH . '/src/Billing/bootstrap.php';

use Data2Md\Log\Logger;
use Stripe\Exception\ApiErrorException;

$logger = new Logger($config->string('APP_DATA_DIR') . '/logs');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    header('Location: /dashboard', true, 303);
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

$user       = $session->user() ?? [];
$userId     = (int) $session->userId();
$customerId = $user['stripe_customer_id'] ?? null;

// Nothing has ever been billed on this account, so there is no portal to open.
if (!is_string($customerId) || $customerId === '') {
    header('Location: /dashboard?billing=nothing', true, 303);
    exit;
}

try {
    $url = $stripe->createPortalSession($customerId, rtrim(APP_URL, '/') . '/dashboard');
} catch (ApiErrorException $e) {
    $logger->app('billing.portal.fail', [
        'user'   => $userId,
        'detail' => $e->getMessage(),
    ], 'error');

    header('Location: /dashboard?billing=error', true, 303);
    exit;
}

$logger->app('billing.portal.open', ['user' => $userId]);

header('Location: ' . $url, true, 303);
