<?php

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));

/** @var \Data2Md\Config\Config $config */
$config = require BASE_PATH . '/src/bootstrap.php';

use Data2Md\Http\ClientIp;
use Data2Md\Log\Logger;
use Data2Md\Security\RateLimiter;
use Data2Md\Security\Validator;
use Data2Md\Storage\Database;
use Data2Md\Storage\FileStorage;
use Data2Md\Storage\MappingStorage;

$dataDir  = $config->string('APP_DATA_DIR');
$logger   = new Logger($dataDir . '/logs');
$clientIp = (new ClientIp($config->list('TRUSTED_PROXIES')))->resolve($_SERVER);
$loggedIp = Logger::truncateIp($clientIp);

// This endpoint is unauthenticated and has no per-request cost worth
// deferring to a slot, but it is still a request loop an anonymous caller
// can drive as fast as the network allows. Logger rotates at 10 MB keeping
// one prior generation, so an unbounded loop of misses can rotate twice and
// discard the entire operational history. Same budget as /api, same
// directory (so Gc's existing rate-limit sweep covers it too), but a
// prefixed identifier so the two endpoints don't share one bucket — a user
// downloading several converted files shouldn't burn the allowance a
// concurrent upload from the same IP needs.
$rateLimiter = new RateLimiter(
    $dataDir . '/ratelimit',
    RATE_LIMIT_MAX,
    RATE_LIMIT_WINDOW,
);

if (!$rateLimiter->isAllowed('download:' . $clientIp)) {
    header('Retry-After: ' . $rateLimiter->retryAfter('download:' . $clientIp));
    http_response_code(429);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Rate limit exceeded.\n";
    exit;
}

$validator = new Validator(MAX_FILE_SIZE, MAX_FILE_SIZE_LABEL);
$hash      = (string) ($_GET['hash'] ?? '');

if (!$validator->isValidHash($hash)) {
    http_response_code(400);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Invalid hash.\n";
    exit;
}

$mappings = new MappingStorage(new Database($dataDir . '/db/mappings.sqlite'));
$files    = new FileStorage($dataDir . '/md', $dataDir . '/tmp');

$mapping = $mappings->find($hash);

if ($mapping === null || !$files->markdownExists($hash)) {
    // 'info', not 'warning': with the rate limit above, a miss is bounded
    // and unremarkable — a stale bookmark or a bad copy-paste, not an
    // operator-worthy event.
    $logger->app('download.miss', ['hash' => $hash, 'ip' => $loggedIp]);

    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Not found.\n";
    exit;
}

// Build a safe download filename from the original name.
$base     = pathinfo($mapping['original_name'], PATHINFO_FILENAME);
$base     = preg_replace('/[^a-zA-Z0-9._\-]/', '_', $base) ?? '';
$base     = trim($base, '_') ?: 'document';
$filename = substr($base, 0, 100) . '.md';

$size = $files->markdownSize($hash);

header('Content-Type: text/markdown; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . $size);
header('Cache-Control: no-store');
header('Pragma: no-cache');

$logger->app('download.ok', ['hash' => $hash, 'bytes' => $size, 'ip' => $loggedIp]);

// Streams from disk instead of loading the document into memory.
$files->streamMarkdown($hash);
