<?php

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));

/** @var \Data2Md\Config\Config $config */
$config = require BASE_PATH . '/src/bootstrap.php';
require BASE_PATH . '/src/I18n/bootstrap.php';

use Data2Md\Converter\ConversionException;
use Data2Md\Converter\MarkdownSanitizer;
use Data2Md\Converter\PdfConverter;
use Data2Md\Http\ClientIp;
use Data2Md\Http\Cors;
use Data2Md\I18n\I18n;
use Data2Md\Log\Logger;
use Data2Md\Response\ApiResponse;
use Data2Md\Runtime\ConversionSemaphore;
use Data2Md\Runtime\Process;
use Data2Md\Runtime\QueueTimeoutException;
use Data2Md\Security\RateLimiter;
use Data2Md\Security\Validator;
use Data2Md\Storage\Database;
use Data2Md\Storage\FileStorage;
use Data2Md\Storage\MappingStorage;

const PREVIEW_BYTES = 8000;

$dataDir = $config->string('APP_DATA_DIR');
$logger  = new Logger($dataDir . '/logs');
$cors    = new Cors($config->list('CORS_ALLOWED_ORIGINS'));
$origin  = $_SERVER['HTTP_ORIGIN'] ?? null;

// X-Content-Type-Options, X-Frame-Options and Referrer-Policy come from the vhost.
foreach ($cors->headersFor(is_string($origin) ? $origin : null) as $name => $value) {
    header("$name: $value");
}

// ── CORS preflight ───────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    $status = $cors->handlePreflight(is_string($origin) ? $origin : null);

    foreach ($cors->headersFor(is_string($origin) ? $origin : null, preflight: true) as $name => $value) {
        header("$name: $value");
    }

    http_response_code($status);
    exit;
}

// ── GET → API documentation page ────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    renderDocs();
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ApiResponse::error('Method not allowed. Use POST.', 405);
}

// ── Bootstrap ────────────────────────────────────────────────────────────────
$database  = new Database($dataDir . '/db/mappings.sqlite');
$mappings  = new MappingStorage($database);
$files     = new FileStorage($dataDir . '/md', $dataDir . '/tmp');
$validator = new Validator(MAX_FILE_SIZE, MAX_FILE_SIZE_LABEL);

$clientIp = (new ClientIp($config->list('TRUSTED_PROXIES')))->resolve($_SERVER);
$loggedIp = Logger::truncateIp($clientIp);

// ── Rate limit ───────────────────────────────────────────────────────────────
$rateLimiter = new RateLimiter(
    $dataDir . '/ratelimit',
    RATE_LIMIT_MAX,
    RATE_LIMIT_WINDOW,
);

if (!$rateLimiter->isAllowed($clientIp)) {
    $logger->app('ratelimit.block', ['ip' => $loggedIp], 'warning');

    header('Retry-After: ' . $rateLimiter->retryAfter());
    ApiResponse::error(
        'Rate limit exceeded. Maximum ' . RATE_LIMIT_MAX . ' requests per ' . RATE_LIMIT_WINDOW . ' seconds.',
        429
    );
}

// ── Validate ─────────────────────────────────────────────────────────────────
$file = $_FILES['file'] ?? null;

if ($file === null) {
    ApiResponse::error('No file provided. Send multipart/form-data with a "file" field.', 400);
}

// A repeated field ("file[]=@a" twice, "format[]=pdf") turns the expected
// scalars into arrays; PHP's superglobals don't enforce the shape for us, and
// Validator::validateUpload()/uploadErrorMessage() take those values typed,
// so an array here would otherwise reach a TypeError before the try below.
if (!is_array($file) || array_filter($file, 'is_array') !== []) {
    ApiResponse::error('Send exactly one file in the "file" field.', 400);
}

$formatInput = $_POST['format'] ?? 'pdf';

if (!is_string($formatInput)) {
    ApiResponse::error('The "format" field must be a single string value.', 400);
}

$format = strtolower(trim($formatInput));

if (!$validator->isAllowedFormat($format)) {
    ApiResponse::error('Unsupported format. Allowed: ' . implode(', ', $validator->supportedFormats()), 400);
}

$uploadErrors = $validator->validateUpload($file, $format);

if ($uploadErrors !== []) {
    ApiResponse::error('Validation failed.', 422, $uploadErrors);
}

// ── Queue for a conversion slot ──────────────────────────────────────────────
$semaphore = new ConversionSemaphore(
    $dataDir . '/tmp',
    $config->int('CONVERT_CONCURRENCY'),
    $config->int('CONVERT_QUEUE_WAIT_SECONDS'),
);

try {
    $slot = $semaphore->acquire();
} catch (QueueTimeoutException $e) {
    $logger->app('convert.rejected', ['ip' => $loggedIp, 'reason' => 'queue_full'], 'warning');

    header('Retry-After: ' . $e->retryAfter());
    ApiResponse::error('The server is busy converting other documents. Please retry shortly.', 503);
}

// ── Convert ──────────────────────────────────────────────────────────────────
$originalPath = null;
$hash         = null;
$startedAt    = microtime(true);

// ApiResponse::success()/error() call exit(), and exit() from inside a try
// or catch does not run an enclosing finally — so the response is deferred
// to $respond and sent only after the try/catch/finally below has completed,
// guaranteeing the cleanup in finally always runs first.
$respond = null;

try {
    $originalName = $validator->sanitizeOriginalName((string) $file['name']);
    $sizeBytes    = (int) $file['size'];

    $hash         = $mappings->generateUniqueHash();
    $originalPath = $files->acceptUpload((string) $file['tmp_name'], $hash);

    $converter = match ($format) {
        'pdf'   => new PdfConverter(
            'pdftotext',
            new Process(
                $config->int('CONVERT_TIMEOUT_SECONDS'),
                Process::priorityPrefix($config->int('CONVERT_NICE')),
            ),
            new MarkdownSanitizer(),
            $dataDir . '/tmp',
        ),
        default => throw new \InvalidArgumentException("Unsupported format: $format"),
    };

    $result = $converter->convertToFile($originalPath, $files->markdownPath($hash));

    $mappings->save($hash, $originalName, $format, $sizeBytes);

    $elapsedMs = (int) round((microtime(true) - $startedAt) * 1000);

    $logger->app('convert.ok', [
        'hash'     => $hash,
        'bytes'    => $sizeBytes,
        'ms'       => $elapsedMs,
        'pages'    => $result->pages,
        'queue_ms' => $slot->waitedMs(),
        'ip'       => $loggedIp,
    ]);

    $logger->audit([
        'hash'          => $hash,
        'original_name' => $originalName,
        'bytes'         => $sizeBytes,
        'ip'            => $clientIp,
        'ua'            => substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 256),
    ]);

    // The preview is read back from the file rather than held in memory, so
    // the response cost is bounded no matter how large the document is.
    $totalBytes = $files->markdownSize($hash);
    $truncated  = $totalBytes > PREVIEW_BYTES;
    $preview    = $files->readMarkdownPrefix($hash, PREVIEW_BYTES);

    if ($truncated) {
        $breakAt = strrpos($preview, "\n");

        if ($breakAt !== false && $breakAt > 0) {
            $preview = substr($preview, 0, $breakAt);
        }
    }

    $respond = static fn () => ApiResponse::success([
        'hash'              => $hash,
        'original_name'     => $originalName,
        'format'            => $format,
        'lines'             => $result->lines,
        'characters'        => $result->characters,
        'pages'             => $result->pages,
        'download_url'      => '/download?hash=' . urlencode($hash),
        'preview'           => $preview,
        'preview_truncated' => $truncated,
    ], 201);

} catch (ConversionException $e) {
    $logger->app('convert.fail', [
        'hash'   => $hash,
        'ip'     => $loggedIp,
        'ms'     => (int) round((microtime(true) - $startedAt) * 1000),
        'reason' => $e::class,
        'detail' => $e->detail(),
    ], 'error');

    $respond = static fn () => ApiResponse::error($e->getMessage(), $e->httpStatus());

} catch (\Throwable $e) {
    // The message may name internal paths, so it goes to the log, not the wire.
    $logger->app('convert.fail', [
        'hash'   => $hash,
        'ip'     => $loggedIp,
        'reason' => $e::class,
        'detail' => $e->getMessage(),
    ], 'error');

    $respond = static fn () => ApiResponse::error('Conversion failed.', 500);

} finally {
    if ($originalPath !== null) {
        // The uploaded file is not kept — this runs on success and on failure.
        $files->deleteOriginal($originalPath);
    }

    $slot->release();
}

// Deferred to run after the response is on the wire, not before: Gc::run()
// globs the whole markdown directory and scans the mappings table, so its
// cost grows with the total number of conversions ever made, not with this
// request. A register_shutdown_function callback still runs after
// ApiResponse's exit() — finally blocks do not, see the note on $respond
// above — and by then ApiResponse::send() has already flushed the response
// with an explicit Content-Length, so the client does not wait on this.
// A failure here (a busy database, disk pressure) is logged and swallowed:
// there is no response left to fail by this point.
if ($config->float('GC_PROBABILITY') > 0.0) {
    register_shutdown_function(static function () use ($config, $files, $mappings, $logger, $dataDir): void {
        try {
            (new \Data2Md\Storage\Gc(
                files:                  $files,
                mappings:               $mappings,
                logger:                 $logger,
                logDir:                 $dataDir . '/logs',
                rateLimitDir:           $dataDir . '/ratelimit',
                logRetentionDays:       $config->int('LOG_RETENTION_DAYS'),
                auditLogRetentionDays:  $config->int('AUDIT_LOG_RETENTION_DAYS'),
                rateLimitWindowSeconds: $config->int('RATE_LIMIT_WINDOW_SECONDS'),
                convertTimeoutSeconds:  $config->int('CONVERT_TIMEOUT_SECONDS'),
                lockDir:                $dataDir . '/tmp',
            ))->maybeRun($config->float('GC_PROBABILITY'));
        } catch (\Throwable $e) {
            $logger->app('gc.fail', ['reason' => $e::class, 'detail' => $e->getMessage()], 'error');
        }
    });
}

$respond();

// ── Docs renderer ────────────────────────────────────────────────────────────
function renderDocs(): void
{
    header('Content-Type: text/html; charset=utf-8');
    header("Content-Security-Policy: default-src 'self'; style-src 'self' 'unsafe-inline'; script-src 'self' 'unsafe-inline'; img-src 'self' data:");
    // Traefik terminates TLS in front of this app, so $_SERVER['HTTPS'] is
    // never set here; APP_URL is mandatory precisely so this page does not
    // have to guess. Same shape as src/View/head-meta.php.
    if (defined('APP_URL') && APP_URL !== '') {
        $base = rtrim(APP_URL, '/');
    } else {
        // HTTP_HOST is client-controlled, so keep it to host characters only.
        $secure = ($_SERVER['HTTPS'] ?? '') === 'on'
               || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
        $host   = preg_replace('/[^A-Za-z0-9.\-:]/', '', $_SERVER['HTTP_HOST'] ?? '');
        $base   = ($secure ? 'https' : 'http') . '://' . substr($host ?: 'localhost', 0, 255);
    }
    $base = htmlspecialchars($base, ENT_QUOTES);
    $back = htmlspecialchars(I18n::sanitizeBack($_SERVER['REQUEST_URI'] ?? '/api'), ENT_QUOTES);
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
  <span class="ck">"pages"</span>: <span class="cn">3</span>,
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

$markdown = file_get_contents(<span class="cs">'<?= $base ?>'</span> . $data[<span class="cs">'download_url'</span>]);
<span class="ck">echo</span> $markdown;</pre>
                    </div>

                    <h3>Python</h3>
                    <div class="code-block-wrap">
                        <button type="button" class="code-copy" data-copy><?= t('api.copy') ?></button>
<pre class="code-block"><span class="ck">import</span> requests

<span class="ck">with</span> <span class="ck">open</span>(<span class="cs">'document.pdf'</span>, <span class="cs">'rb'</span>) <span class="ck">as</span> f:
    r = requests.post(<span class="cs">'<?= $base ?>/api'</span>, files={<span class="cs">'file'</span>: f}, data={<span class="cs">'format'</span>: <span class="cs">'pdf'</span>})

data = r.json()
markdown = requests.get(<span class="cs">'<?= $base ?>'</span> + data[<span class="cs">'download_url'</span>]).text
<span class="ck">print</span>(markdown)</pre>
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
                <a href="/terms"><?= t('nav.terms') ?></a>
                <a href="/privacy"><?= t('nav.privacy') ?></a>
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
