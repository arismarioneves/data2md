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
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header("Content-Security-Policy: default-src 'self'; style-src 'self' 'unsafe-inline'; script-src 'self' 'unsafe-inline'; img-src 'self' data:");
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $base   = $scheme . '://' . htmlspecialchars($_SERVER['HTTP_HOST'] ?? 'localhost', ENT_QUOTES);
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>data2md — API Reference</title>
    <link rel="stylesheet" href="/assets/css/landing.css">
    <link rel="stylesheet" href="/assets/css/docs.css">
</head>
<body>

<header class="l-header" id="l-header">
    <div class="l-container">
        <div class="l-header-inner">
            <a href="/" class="l-logo" aria-label="data2md home">
                <span class="l-logo-mark" aria-hidden="true"></span>
                <span class="l-logo-name">data2md</span>
            </a>
            <nav class="l-nav">
                <a href="/" class="l-nav-link" data-hide-sm>About</a>
                <a href="/api" class="l-nav-link">API</a>
                <a href="/app" class="l-nav-cta">
                    Open app
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
                </a>
            </nav>
        </div>
    </div>
</header>

<main class="l-main">
    <div class="l-container">
        <div class="docs-main">
            <article class="docs">

                <header class="docs-hero">
                    <span class="docs-eyebrow">// rest api</span>
                    <h1 class="docs-title">API <em>Reference</em></h1>
                    <p class="docs-sub">
                        Integrate data2md in your workflow with a single HTTP POST.
                        Streamed conversion via <code>pdftotext</code>, max <?= MAX_FILE_SIZE_LABEL ?> per request.
                    </p>
                </header>

                <section class="docs-section">
                    <h2>Endpoint</h2>
                    <div class="endpoint-pill">
                        <span class="method">POST</span>
                        <code class="endpoint-url"><?= $base ?>/api</code>
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
                    <h2>Success response <span class="code-tag">201 Created</span></h2>
                    <div class="code-block-wrap">
                        <button type="button" class="code-copy" data-copy>Copy</button>
<pre class="code-block">{
  <span class="ck">"success"</span>: <span class="cn">true</span>,
  <span class="ck">"hash"</span>: <span class="cs">"a3f9c12b84e7d01"</span>,
  <span class="ck">"original_name"</span>: <span class="cs">"report.pdf"</span>,
  <span class="ck">"format"</span>: <span class="cs">"pdf"</span>,
  <span class="ck">"lines"</span>: <span class="cn">148</span>,
  <span class="ck">"characters"</span>: <span class="cn">9342</span>,
  <span class="ck">"download_url"</span>: <span class="cs">"/download?hash=a3f9c12b84e7d01"</span>,
  <span class="ck">"markdown"</span>: <span class="cs">"# Report Title\n\n..."</span>
}</pre>
                    </div>
                </section>

                <section class="docs-section">
                    <h2>Error response <span class="code-tag code-tag--err">4xx · 5xx</span></h2>
                    <div class="code-block-wrap">
                        <button type="button" class="code-copy" data-copy>Copy</button>
<pre class="code-block">{
  <span class="ck">"success"</span>: <span class="cn">false</span>,
  <span class="ck">"message"</span>: <span class="cs">"Validation failed."</span>,
  <span class="ck">"errors"</span>: [<span class="cs">"File content does not match allowed types for this format."</span>]
}</pre>
                    </div>
                </section>

                <section class="docs-section">
                    <h2>Rate limits</h2>
                    <p>
                        <?= RATE_LIMIT_API ?> requests per minute per IP. Exceeding returns
                        <code>429 Too Many Requests</code> with a <code>Retry-After: <?= RATE_LIMIT_WINDOW ?></code> header.
                    </p>
                </section>

                <section class="docs-section">
                    <h2>Examples</h2>

                    <h3>cURL</h3>
                    <div class="code-block-wrap">
                        <button type="button" class="code-copy" data-copy>Copy</button>
<pre class="code-block">curl -X POST <?= $base ?>/api \
  -F <span class="cs">"file=@document.pdf"</span> \
  -F <span class="cs">"format=pdf"</span></pre>
                    </div>

                    <h3>PHP</h3>
                    <div class="code-block-wrap">
                        <button type="button" class="code-copy" data-copy>Copy</button>
<pre class="code-block"><span class="cm">// stream a file via cURL</span>
$ch = curl_init(<span class="cs">'<?= $base ?>/api'</span>);
curl_setopt_array($ch, [
    CURLOPT_POST           =&gt; <span class="cn">true</span>,
    CURLOPT_RETURNTRANSFER =&gt; <span class="cn">true</span>,
    CURLOPT_POSTFIELDS     =&gt; [
        <span class="cs">'file'</span>   =&gt; <span class="ck">new</span> CURLFile(<span class="cs">'document.pdf'</span>, <span class="cs">'application/pdf'</span>, <span class="cs">'document.pdf'</span>),
        <span class="cs">'format'</span> =&gt; <span class="cs">'pdf'</span>,
    ],
]);
$data = json_decode(curl_exec($ch), <span class="cn">true</span>);
curl_close($ch);
<span class="ck">echo</span> $data[<span class="cs">'markdown'</span>];</pre>
                    </div>

                    <h3>Python</h3>
                    <div class="code-block-wrap">
                        <button type="button" class="code-copy" data-copy>Copy</button>
<pre class="code-block"><span class="ck">import</span> requests

<span class="ck">with</span> <span class="ck">open</span>(<span class="cs">'document.pdf'</span>, <span class="cs">'rb'</span>) <span class="ck">as</span> f:
    r = requests.post(<span class="cs">'<?= $base ?>/api'</span>, files={<span class="cs">'file'</span>: f}, data={<span class="cs">'format'</span>: <span class="cs">'pdf'</span>})

data = r.json()
<span class="ck">print</span>(data[<span class="cs">'markdown'</span>])</pre>
                    </div>
                </section>

            </article>
        </div>
    </div>
</main>

<footer class="l-footer">
    <div class="l-container">
        <div class="l-footer-inner">
            <p class="l-footer-text">
                <strong>data2md</strong> API — anonymous by design.
            </p>
            <nav class="l-footer-links">
                <a href="/">Home</a>
                <a href="/app">Converter</a>
            </nav>
        </div>
    </div>
</footer>

<script>
(function(){
    var header = document.getElementById('l-header');
    var onScroll = function(){
        if (window.scrollY > 8) header.classList.add('is-scrolled');
        else header.classList.remove('is-scrolled');
    };
    document.addEventListener('scroll', onScroll, { passive:true });
    onScroll();

    document.querySelectorAll('[data-copy]').forEach(function(btn){
        btn.addEventListener('click', function(){
            var pre = btn.parentElement.querySelector('.code-block');
            if (!pre) return;
            var text = pre.textContent;
            var done = function(){
                var orig = btn.textContent;
                btn.textContent = 'Copied';
                btn.classList.add('is-copied');
                setTimeout(function(){
                    btn.textContent = orig;
                    btn.classList.remove('is-copied');
                }, 1600);
            };
            if (navigator.clipboard && window.isSecureContext){
                navigator.clipboard.writeText(text).then(done).catch(function(){ legacy(text, done); });
            } else {
                legacy(text, done);
            }
        });
    });
    function legacy(text, cb){
        var ta = document.createElement('textarea');
        ta.value = text;
        ta.style.cssText = 'position:fixed;opacity:0;pointer-events:none';
        document.body.appendChild(ta);
        ta.focus(); ta.select();
        try { document.execCommand('copy'); cb(); } catch(_) {}
        document.body.removeChild(ta);
    }
})();
</script>

</body>
</html>
    <?php
}
