<?php

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));

/** @var \Data2Md\Config\Config $config */
$config = require BASE_PATH . '/src/bootstrap.php';

require BASE_PATH . '/src/I18n/bootstrap.php';

use Data2Md\Converter\PdfConverter;
use Data2Md\I18n\I18n;
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
$rateLimiter    = new RateLimiter(BASE_PATH . '/storage/rate_limits', RATE_LIMIT_MAX, RATE_LIMIT_WINDOW);

// ── Rate limit ───────────────────────────────────────────────────────────────
$clientIp = trim(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0')[0]);

if (!$rateLimiter->isAllowed($clientIp)) {
    header('Retry-After: ' . RATE_LIMIT_WINDOW);
    ApiResponse::error('Rate limit exceeded. Maximum ' . RATE_LIMIT_MAX . ' requests per minute.', 429);
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

    // Truncate the inline preview at a clean line boundary. Full markdown is
    // always available via download_url.
    $previewLimit = 8000;
    $totalChars   = mb_strlen($markdown);
    $truncated    = $totalChars > $previewLimit;
    $preview      = $markdown;
    if ($truncated) {
        $breakAt = strrpos(substr($markdown, 0, $previewLimit), "\n");
        $preview = substr($markdown, 0, $breakAt !== false && $breakAt > 0 ? $breakAt : $previewLimit);
    }

    ApiResponse::success([
        'hash'              => $hash,
        'original_name'     => $originalName,
        'format'            => $format,
        'lines'             => substr_count($markdown, "\n") + 1,
        'characters'        => $totalChars,
        'download_url'      => '/download?hash=' . urlencode($hash),
        'preview'           => $preview,
        'preview_truncated' => $truncated,
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
    $back   = htmlspecialchars(I18n::sanitizeBack($_SERVER['REQUEST_URI'] ?? '/api'), ENT_QUOTES);
    ?>
<!DOCTYPE html>
<html lang="<?= I18n::current() ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php
    $metaTitle = t('api.title', APP_NAME);
    $metaDesc  = t('api.meta.desc');
    require BASE_PATH . '/src/View/head-meta.php';
    ?>
    <link rel="stylesheet" href="/assets/css/landing.css">
    <link rel="stylesheet" href="/assets/css/docs.css">
</head>
<body>

<header class="l-header" id="l-header">
    <div class="l-container">
        <div class="l-header-inner">
            <a href="/" class="l-logo" aria-label="<?= htmlspecialchars(APP_NAME, ENT_QUOTES) ?> home">
                <img class="l-logo-mark" src="/assets/img/icon.svg" width="30" height="30" alt="" aria-hidden="true">
                <span class="l-logo-name"><?= htmlspecialchars(APP_NAME) ?></span>
            </a>
            <nav class="l-nav">
                <a href="/" class="l-nav-link" data-hide-sm><?= t('nav.about') ?></a>
                <a href="/api" class="l-nav-link"><?= t('nav.api') ?></a>
                <a href="/app" class="l-nav-cta">
                    <?= t('nav.app') ?>
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
                </a>
                <span class="l-lang" role="group" aria-label="<?= htmlspecialchars(t('lang.aria'), ENT_QUOTES) ?>">
                    <a href="/lang?to=en&amp;back=<?= $back ?>" class="<?= I18n::current() === 'en' ? 'is-active' : '' ?>"><?= t('lang.label.en') ?></a>
                    <span class="l-lang-sep" aria-hidden="true">·</span>
                    <a href="/lang?to=pt&amp;back=<?= $back ?>" class="<?= I18n::current() === 'pt' ? 'is-active' : '' ?>"><?= t('lang.label.pt') ?></a>
                </span>
            </nav>
        </div>
    </div>
</header>

<main class="l-main">
    <div class="l-container">
        <div class="docs-main">
            <article class="docs">

                <header class="docs-hero">
                    <span class="docs-eyebrow"><?= t('api.eyebrow') ?></span>
                    <h1 class="docs-title"><?= t('api.heading') ?></h1>
                    <p class="docs-sub"><?= t('api.lead', htmlspecialchars(APP_NAME), MAX_FILE_SIZE_LABEL) ?></p>
                </header>

                <section class="docs-section">
                    <h2><?= t('api.section.endpoint') ?></h2>
                    <div class="endpoint-pill">
                        <span class="method">POST</span>
                        <code class="endpoint-url"><?= $base ?>/api</code>
                    </div>
                    <p><?= t('api.endpoint.ct') ?></p>
                </section>

                <section class="docs-section">
                    <h2><?= t('api.section.fields') ?></h2>
                    <div class="docs-table-wrap">
                        <table class="docs-table">
                            <thead><tr><th><?= t('api.field.col.field') ?></th><th><?= t('api.field.col.type') ?></th><th><?= t('api.field.col.required') ?></th><th><?= t('api.field.col.desc') ?></th></tr></thead>
                            <tbody>
                                <tr><td><code>file</code></td><td>file</td><td><?= t('api.field.required.yes') ?></td><td><?= t('api.field.file.desc', MAX_FILE_SIZE_LABEL) ?></td></tr>
                                <tr><td><code>format</code></td><td>string</td><td><?= t('api.field.required.no') ?></td><td><?= t('api.field.format.desc') ?></td></tr>
                            </tbody>
                        </table>
                    </div>
                </section>

                <section class="docs-section">
                    <h2><?= t('api.section.success') ?> <span class="code-tag"><?= t('api.tag.success') ?></span></h2>
                    <p><?= t('api.success.note') ?></p>
                    <div class="code-block-wrap">
                        <button type="button" class="code-copy" data-copy><?= t('api.copy') ?></button>
<pre class="code-block">{
  <span class="ck">"success"</span>: <span class="cn">true</span>,
  <span class="ck">"hash"</span>: <span class="cs">"a3f9c12b84e7d01"</span>,
  <span class="ck">"original_name"</span>: <span class="cs">"report.pdf"</span>,
  <span class="ck">"format"</span>: <span class="cs">"pdf"</span>,
  <span class="ck">"lines"</span>: <span class="cn">148</span>,
  <span class="ck">"characters"</span>: <span class="cn">9342</span>,
  <span class="ck">"download_url"</span>: <span class="cs">"/download?hash=a3f9c12b84e7d01"</span>,
  <span class="ck">"preview"</span>: <span class="cs">"# Report Title\n\n..."</span>,
  <span class="ck">"preview_truncated"</span>: <span class="cn">false</span>
}</pre>
                    </div>
                </section>

                <section class="docs-section">
                    <h2><?= t('api.section.error') ?> <span class="code-tag code-tag--err"><?= t('api.tag.error') ?></span></h2>
                    <div class="code-block-wrap">
                        <button type="button" class="code-copy" data-copy><?= t('api.copy') ?></button>
<pre class="code-block">{
  <span class="ck">"success"</span>: <span class="cn">false</span>,
  <span class="ck">"message"</span>: <span class="cs">"Validation failed."</span>,
  <span class="ck">"errors"</span>: [<span class="cs">"File content does not match allowed types for this format."</span>]
}</pre>
                    </div>
                </section>

                <section class="docs-section">
                    <h2><?= t('api.section.rate') ?></h2>
                    <p><?= t('api.rate.body', RATE_LIMIT_MAX, RATE_LIMIT_WINDOW) ?></p>
                </section>

                <section class="docs-section">
                    <h2><?= t('api.section.examples') ?></h2>

                    <h3>cURL</h3>
                    <div class="code-block-wrap">
                        <button type="button" class="code-copy" data-copy><?= t('api.copy') ?></button>
<pre class="code-block">curl -X POST <?= $base ?>/api \
  -F <span class="cs">"file=@document.pdf"</span> \
  -F <span class="cs">"format=pdf"</span></pre>
                    </div>

                    <h3>PHP</h3>
                    <div class="code-block-wrap">
                        <button type="button" class="code-copy" data-copy><?= t('api.copy') ?></button>
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
                        <button type="button" class="code-copy" data-copy><?= t('api.copy') ?></button>
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
            <p class="l-footer-text"><?= t('footer.api.text', htmlspecialchars(APP_NAME)) ?></p>
            <nav class="l-footer-links">
                <a href="/"><?= t('footer.home') ?></a>
                <a href="/app"><?= t('footer.converter') ?></a>
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
                btn.textContent = <?= json_encode(t('api.copied'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_HEX_APOS) ?>;
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
