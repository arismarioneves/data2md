<?php

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));

/** @var \Data2Md\Config\Config $config */
$config = require BASE_PATH . '/src/bootstrap.php';
require BASE_PATH . '/src/Auth/bootstrap.php';

use Data2Md\Http\ClientIp;
use Data2Md\Http\SameOrigin;
use Data2Md\Log\Logger;
use Data2Md\Response\ApiResponse;
use Data2Md\Storage\Gc;

// The browser's conversion endpoint. It reads the session cookie and never an
// Authorization header, which is the mirror of /api and keeps the two channels
// from blurring into one. No CORS headers: this is not a cross-origin API.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ApiResponse::error('Method not allowed. Use POST.', 405);
}

if (!SameOrigin::isAllowed($_SERVER, APP_URL)) {
    ApiResponse::error('Cross-origin conversions are not accepted here. Use /api with a token.', 403);
}

$dataDir = $config->string('APP_DATA_DIR');
$logger  = new Logger($dataDir . '/logs');

require BASE_PATH . '/src/Convert/bootstrap.php';

$clientIp = (new ClientIp($config->list('TRUSTED_PROXIES')))->resolve($_SERVER);

$outcome = $pipeline->handle(
    $_FILES,
    $_POST,
    $clientIp,
    (string) ($_SERVER['HTTP_USER_AGENT'] ?? ''),
    $session?->userId(),
);

Gc::registerShutdownSweep($config, $logger, $database, $files, $mappings);

foreach ($outcome->headers as $name => $value) {
    header("$name: $value");
}

$outcome->isSuccess()
    ? ApiResponse::success($outcome->payload, $outcome->status)
    : ApiResponse::error((string) $outcome->message, $outcome->status, $outcome->errors);
