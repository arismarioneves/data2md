<?php

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
require BASE_PATH . '/config.php';

@ini_set('max_execution_time', (string) APP_MAX_EXECUTION_TIME);
@ini_set('memory_limit', APP_MEMORY_LIMIT);

require BASE_PATH . '/vendor/autoload.php';

use Data2Md\Converter\PdfConverter;
use Data2Md\Response\ApiResponse;
use Data2Md\Security\RateLimiter;
use Data2Md\Security\Validator;
use Data2Md\Storage\FileStorage;
use Data2Md\Storage\MappingStorage;

// ── Security headers ────────────────────────────────────────────────────────
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: strict-origin-when-cross-origin');

// ── CORS preflight ───────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// ── GET → API documentation page ────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    renderDocs();
    exit;
}

// ── Only POST beyond this point ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ApiResponse::error('Method not allowed. Use POST.', 405);
}

// ── Bootstrap ────────────────────────────────────────────────────────────────
$mappingStorage = new MappingStorage(BASE_PATH . '/mappings');
$fileStorage    = new FileStorage(BASE_PATH . '/data', BASE_PATH . '/md', $mappingStorage);
$validator      = new Validator();
$rateLimiter    = new RateLimiter(BASE_PATH . '/storage/rate_limits', RATE_LIMIT_API, RATE_LIMIT_WINDOW);

// ── Rate limit ───────────────────────────────────────────────────────────────
$clientIp = trim(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0')[0]);

if (!$rateLimiter->isAllowed($clientIp)) {
    header('Retry-After: ' . RATE_LIMIT_WINDOW);
    ApiResponse::error('Rate limit exceeded. Maximum ' . RATE_LIMIT_API . ' requests per minute.', 429);
}

// ── Validate file ────────────────────────────────────────────────────────────
$file = $_FILES['file'] ?? null;

if ($file === null) {
    ApiResponse::error('No file provided. Send multipart/form-data with a "file" field.', 400);
}

$format = strtolower(trim($_POST['format'] ?? 'pdf'));

if (!$validator->isAllowedFormat($format)) {
    ApiResponse::error(
        'Unsupported format. Allowed: ' . implode(', ', $validator->supportedFormats()),
        400
    );
}

$uploadErrors = $validator->validateUpload($file, $format);
if ($uploadErrors !== []) {
    ApiResponse::error('Validation failed.', 422, $uploadErrors);
}

// ── Convert ──────────────────────────────────────────────────────────────────
try {
    $originalName = $validator->sanitizeOriginalName($file['name']);
    $hash         = $fileStorage->storeUploadedFile($file, $originalName, $format);

    $converter = match ($format) {
        'pdf'   => new PdfConverter(),
        default => throw new \InvalidArgumentException("Unsupported format: $format"),
    };

    $filePath = $fileStorage->getFilePath($hash, $format);
    $markdown = $converter->convert($filePath);
    $fileStorage->storeMarkdown($hash, $markdown);

    ApiResponse::success([
        'hash'          => $hash,
        'original_name' => $originalName,
        'format'        => $format,
        'lines'         => substr_count($markdown, "\n") + 1,
        'characters'    => mb_strlen($markdown),
        'download_url'  => '/download?hash=' . urlencode($hash),
        'markdown'      => $markdown,
    ], 201);

} catch (\Exception $e) {
    ApiResponse::error('Conversion failed: ' . $e->getMessage(), 500);
}

// ── Docs renderer ────────────────────────────────────────────────────────────
function renderDocs(): void
{
    header('Content-Type: text/html; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $base   = $scheme . '://' . htmlspecialchars($_SERVER['HTTP_HOST'] ?? 'localhost', ENT_QUOTES);
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>data2md API Reference</title>
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
<header class="header">
    <div class="container">
        <div class="header-inner">
            <a href="/" class="logo"><span class="logo-mark">d2md</span></a>
            <nav><a href="/app" class="nav-link">Converter</a></nav>
        </div>
    </div>
</header>
<main class="main">
    <div class="container docs">
        <h1 class="docs-title">API Reference</h1>
        <p class="docs-sub">Integrate data2md directly in your workflow via a simple HTTP POST.</p>

        <section class="docs-section">
            <h2>Endpoint</h2>
            <div class="endpoint-pill">
                <span class="method">POST</span>
                <code><?= $base ?>/api</code>
            </div>
            <p>Content-Type: <code>multipart/form-data</code></p>
        </section>

        <section class="docs-section">
            <h2>Request fields</h2>
            <table class="docs-table">
                <thead><tr><th>Field</th><th>Type</th><th>Required</th><th>Description</th></tr></thead>
                <tbody>
                    <tr><td><code>file</code></td><td>file</td><td>Yes</td><td>The file to convert. Max <?= MAX_FILE_SIZE_LABEL ?>.</td></tr>
                    <tr><td><code>format</code></td><td>string</td><td>No</td><td>File format. Default: <code>pdf</code>. Currently supported: <code>pdf</code>.</td></tr>
                </tbody>
            </table>
        </section>

        <section class="docs-section">
            <h2>Success response <span class="code-tag">201</span></h2>
            <pre class="code-block">{
  "success": true,
  "hash": "a3f9c12b84e7d01",
  "original_name": "report.pdf",
  "format": "pdf",
  "lines": 148,
  "characters": 9342,
  "download_url": "/download?hash=a3f9c12b84e7d01",
  "markdown": "# Report Title\n\n..."
}</pre>
        </section>

        <section class="docs-section">
            <h2>Error response</h2>
            <pre class="code-block">{
  "success": false,
  "message": "Validation failed.",
  "errors": ["File content does not match allowed types for this format."]
}</pre>
        </section>

        <section class="docs-section">
            <h2>Rate limits</h2>
            <p><?= RATE_LIMIT_API ?> requests per minute per IP. Exceeding returns <code>429 Too Many Requests</code> with a <code>Retry-After: <?= RATE_LIMIT_WINDOW ?></code> header.</p>
        </section>

        <section class="docs-section">
            <h2>Examples</h2>

            <h3>cURL</h3>
            <pre class="code-block">curl -X POST <?= $base ?>/api \
  -F "file=@document.pdf" \
  -F "format=pdf"</pre>

            <h3>PHP (stream)</h3>
            <pre class="code-block">$ch = curl_init('<?= $base ?>/api');
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POSTFIELDS     => [
        'file'   => new CURLFile('document.pdf', 'application/pdf', 'document.pdf'),
        'format' => 'pdf',
    ],
]);
$data = json_decode(curl_exec($ch), true);
curl_close($ch);
echo $data['markdown'];</pre>

            <h3>Python (requests)</h3>
            <pre class="code-block">import requests

with open('document.pdf', 'rb') as f:
    r = requests.post('<?= $base ?>/api', files={'file': f}, data={'format': 'pdf'})

data = r.json()
print(data['markdown'])</pre>
        </section>
    </div>
</main>
<footer class="footer"><div class="container"><p>data2md API &mdash; <a href="/app" class="nav-link">Back to converter</a></p></div></footer>
</body>
</html>
    <?php
}
