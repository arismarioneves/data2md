<?php

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));

/** @var \Data2Md\Config\Config $config */
$config = require BASE_PATH . '/src/bootstrap.php';

$checks = require BASE_PATH . '/src/health-checks.php';
$healthy = !in_array(false, $checks, true);

http_response_code($healthy ? 200 : 503);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

// Check results are names and booleans only — no paths, nothing that maps the host.
echo json_encode([
    'status'  => $healthy ? 'ok' : 'fail',
    'version' => APP_VERSION,
    'checks'  => $checks,
], JSON_UNESCAPED_SLASHES);
