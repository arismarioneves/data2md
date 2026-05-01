<?php
// Dev-only router for `php -S` — mimics .htaccess rewrite for clean URLs.
// Not used in production (Apache handles it via .htaccess).

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Direct file (asset, .php, etc) — let the server serve it
$direct = __DIR__ . $path;
if ($path !== '/' && file_exists($direct) && !is_dir($direct)) {
    return false;
}

// Rewrite /foo → /foo.php if it exists
$candidate = __DIR__ . $path . '.php';
if (file_exists($candidate) && !is_dir($candidate)) {
    require $candidate;
    return true;
}

// Default: serve index.php
require __DIR__ . '/index.php';
return true;
