<?php

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));

/** @var \Data2Md\Config\Config $config */
$config = require BASE_PATH . '/src/bootstrap.php';
require BASE_PATH . '/src/Auth/bootstrap.php';

use Data2Md\Auth\Session;
use Data2Md\Log\Logger;

/** @var Session|null $session */
if ($session === null) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Not found.\n";
    exit;
}

// POST only, so a link or an image cannot sign someone out.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    header('Location: /', true, 303);
    exit;
}

if ($session->isAuthenticated()) {
    if (!$session->checkCsrf($_POST['csrf'] ?? null)) {
        http_response_code(403);
        header('Content-Type: text/plain; charset=utf-8');
        echo "Invalid request token.\n";
        exit;
    }

    (new Logger($config->string('APP_DATA_DIR') . '/logs'))
        ->app('auth.session.destroy', ['user' => $session->userId()]);

    $session->destroy();
}

header('Cache-Control: private, no-store');
header('Location: /', true, 303);
exit;
