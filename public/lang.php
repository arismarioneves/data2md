<?php

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
$config = require BASE_PATH . '/src/bootstrap.php';
require BASE_PATH . '/src/I18n/bootstrap.php';

use Data2Md\I18n\I18n;

$to   = (string) ($_GET['to']   ?? $_POST['to']   ?? '');
$back = (string) ($_GET['back'] ?? $_POST['back'] ?? '/');

$back = I18n::sanitizeBack($back);

if (in_array($to, I18n::SUPPORTED, true)) {
    // Traefik terminates TLS in front of this container, so $_SERVER['HTTPS']
    // is never set here.
    $secure = str_starts_with(APP_URL, 'https://');
    setcookie(I18n::COOKIE, $to, [
        'expires'  => time() + 60 * 60 * 24 * 365,
        'path'     => '/',
        'samesite' => 'Lax',
        'secure'   => $secure,
        'httponly' => false,
    ]);
}

header('Location: ' . $back, true, 303);
exit;
