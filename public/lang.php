<?php

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
require BASE_PATH . '/src/I18n/I18n.php';

use Data2Md\I18n\I18n;

$to   = (string) ($_GET['to']   ?? $_POST['to']   ?? '');
$back = (string) ($_GET['back'] ?? $_POST['back'] ?? '/');

$back = I18n::sanitizeBack($back);

if (in_array($to, I18n::SUPPORTED, true)) {
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    setcookie(I18n::COOKIE, $to, [
        'expires'  => time() + 60 * 60 * 24 * 365,
        'path'     => '/',
        'samesite' => 'Lax',
        'secure'   => $secure,
        'httponly' => false,
    ]);
}

header('Location: ' . $back, true, 303);
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
exit;
