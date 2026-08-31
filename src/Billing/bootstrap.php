<?php

declare(strict_types=1);

/**
 * Shared setup for the billing endpoints.
 *
 * Defines $billingDatabase, $billingStore, $billingUsers and $stripe, and sends
 * a 404 when billing is switched off — an endpoint that is not configured
 * should not advertise that it exists.
 *
 * Requires $config, and $session from src/Auth/bootstrap.php.
 *
 * @var \Data2Md\Config\Config $config
 * @var \Data2Md\Auth\Session|null $session
 */

use Data2Md\Auth\UserStore;
use Data2Md\Billing\BillingStore;
use Data2Md\Billing\StripeGateway;
use Data2Md\Storage\Database;

if (!$config->bool('BILLING_ENABLED') || $session === null) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Not found.\n";
    exit;
}

$billingDatabase = new Database($config->string('APP_DATA_DIR') . '/db/mappings.sqlite');
$billingStore    = new BillingStore($billingDatabase);
$billingUsers    = new UserStore($billingDatabase);
$stripe          = StripeGateway::fromConfig($config);
