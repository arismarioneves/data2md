<?php

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));

/** @var \Data2Md\Config\Config $config */
$config = require BASE_PATH . '/src/bootstrap.php';

use Data2Md\Http\ClientIp;
use Data2Md\Log\Logger;
use Data2Md\Security\Validator;
use Data2Md\Storage\Database;
use Data2Md\Storage\FileStorage;
use Data2Md\Storage\MappingStorage;

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');

$dataDir  = $config->string('APP_DATA_DIR');
$logger   = new Logger($dataDir . '/logs');
$loggedIp = Logger::truncateIp((new ClientIp($config->list('TRUSTED_PROXIES')))->resolve($_SERVER));

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
    $logger->app('download.miss', ['hash' => $hash, 'ip' => $loggedIp], 'warning');

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
