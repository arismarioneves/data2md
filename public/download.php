<?php

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));

/** @var \Data2Md\Config\Config $config */
$config = require BASE_PATH . '/src/bootstrap.php';

require BASE_PATH . '/src/I18n/bootstrap.php';

use Data2Md\Security\Validator;
use Data2Md\Storage\FileStorage;
use Data2Md\Storage\MappingStorage;

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');

$validator = new Validator();
$hash      = $_GET['hash'] ?? '';

if (!$validator->isValidHash($hash)) {
    http_response_code(400);
    echo 'Invalid hash.';
    exit;
}

$mappingStorage = new MappingStorage(BASE_PATH . '/mappings');
$fileStorage    = new FileStorage(BASE_PATH . '/data', BASE_PATH . '/md', $mappingStorage);

$mapping = $mappingStorage->find($hash);

if ($mapping === null) {
    http_response_code(404);
    echo 'File not found.';
    exit;
}

$content = $fileStorage->getMarkdownContent($hash);

if ($content === null) {
    http_response_code(404);
    echo 'Markdown file not found.';
    exit;
}

// Build a safe download filename from the original name
$base     = pathinfo($mapping['original_name'], PATHINFO_FILENAME);
$base     = preg_replace('/[^a-zA-Z0-9._\-]/', '_', $base);
$base     = trim($base, '_') ?: 'document';
$filename = substr($base, 0, 100) . '.md';

header('Content-Type: text/markdown; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . strlen($content));
header('Cache-Control: no-store');
header('Pragma: no-cache');

echo $content;
