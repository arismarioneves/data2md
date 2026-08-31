<?php

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));

/** @var \Data2Md\Config\Config $config */
$config = require BASE_PATH . '/src/bootstrap.php';
require BASE_PATH . '/src/I18n/bootstrap.php';

use Data2Md\Auth\Actor;
use Data2Md\Auth\ApiTokenStore;
use Data2Md\Auth\TierResolver;
use Data2Md\Http\BearerToken;
use Data2Md\Http\ClientIp;
use Data2Md\Http\Cors;
use Data2Md\I18n\I18n;
use Data2Md\Log\Logger;
use Data2Md\Response\ApiResponse;
use Data2Md\Security\RateLimiterFactory;
use Data2Md\Security\RateLimitState;
use Data2Md\Storage\Database;
use Data2Md\Storage\Gc;

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

// The vhost rewrites /api/me to this file, so the route comes from the path
// rather than from a second file on disk: one front controller, one place the
// credential is read.
$route  = trim((string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH), '/');
$method = (string) $_SERVER['REQUEST_METHOD'];

// ── GET /api → documentation page ───────────────────────────────────────────
if ($route === 'api' && ($method === 'GET' || $method === 'HEAD')) {
    // Only the docs page resolves a session. Everything below reads
    // Authorization and never a cookie, which is what keeps CSRF away from the
    // one route that answers cross-origin.
    require BASE_PATH . '/src/Auth/bootstrap.php';

    renderDocs($session, $apiTier);
    exit;
}

// ── Everything below is JSON, and authenticated ─────────────────────────────
$clientIp = (new ClientIp($config->list('TRUSTED_PROXIES')))->resolve($_SERVER);
$loggedIp = Logger::truncateIp($clientIp);

if (!$config->bool('ACCOUNTS_ENABLED')) {
    // Not "fall back to anonymous": a flag about sign-in must never be what
    // decides who may call the API. Without accounts no token can exist, so say
    // that, rather than quietly reopening the endpoint.
    ApiResponse::error(
        'The API is unavailable: this deployment has accounts switched off.',
        503,
        code: 'api_unavailable'
    );
}

$tiers        = TierResolver::fromConfig($config);
$rateLimiters = new RateLimiterFactory($dataDir . '/ratelimit');
$tokens       = new ApiTokenStore(new Database($dataDir . '/db/mappings.sqlite'));
$presented    = BearerToken::fromRequest($_SERVER);

if ($presented === null) {
    header('WWW-Authenticate: Bearer realm="api"');
    $logger->app('api.auth.reject', ['ip' => $loggedIp, 'reason' => 'no_token'], 'warning');

    ApiResponse::error(
        'Authentication required. Send an Authorization: Bearer <token> header.',
        401,
        code: 'token_required'
    );
}

$user = $tokens->authenticate($presented);

if ($user === null) {
    header('WWW-Authenticate: Bearer realm="api"');
    $logger->app('api.auth.reject', ['ip' => $loggedIp, 'reason' => 'unknown_token'], 'warning');

    ApiResponse::error(
        'That token does not exist. Generate a new one from your dashboard.',
        401,
        code: 'token_invalid'
    );
}

$actor = Actor::forApi($user, $clientIp, $tiers);
$quota = $actor->primaryQuota();

// ── GET /api/me → plan, limits and window ───────────────────────────────────
if ($route === 'api/me') {
    if ($method !== 'GET' && $method !== 'HEAD') {
        ApiResponse::error('Method not allowed. Use GET.', 405);
    }

    // Open to every plan on purpose: this is how a caller finds out why a
    // conversion was refused. It charges nothing, so a free token may ask.
    $state = $rateLimiters->make($quota->maxRequests, $quota->windowSeconds)->snapshot($quota->key);
    $token = $tokens->describeFor((int) $actor->userId);

    sendRateLimitHeaders($state);
    header('Cache-Control: private, no-store');

    ApiResponse::success([
        'email'      => (string) $user['email'],
        'plan'       => $actor->plan,
        'api_access' => $actor->tier->allowsApi(),
        'limits'     => [
            'max_conversions'     => $actor->tier->maxConversions,
            'window_seconds'      => $actor->tier->windowSeconds,
            'max_file_size_bytes' => $actor->tier->maxFileSizeBytes(),
        ],
        'rate_limit' => [
            'limit'     => $state->limit,
            'remaining' => $state->remaining(),
            'reset'     => $state->resetAt,
        ],
        'subscription' => [
            'status'               => $user['subscription_status'],
            'current_period_end'   => isset($user['current_period_end'])
                ? (int) $user['current_period_end']
                : null,
            'cancel_at_period_end' => (bool) ($user['cancel_at_period_end'] ?? 0),
        ],
        'token' => [
            'prefix'       => (string) $token['prefix'],
            'last4'        => (string) $token['last4'],
            'created_at'   => (int) $token['created_at'],
            'rotated_at'   => isset($token['rotated_at']) ? (int) $token['rotated_at'] : null,
            'last_used_at' => isset($token['last_used_at']) ? (int) $token['last_used_at'] : null,
        ],
    ]);
}

if ($route !== 'api') {
    ApiResponse::error('Not found.', 404);
}

if ($method !== 'POST') {
    ApiResponse::error('Method not allowed. Use POST.', 405);
}

if (!$actor->tier->allowsApi()) {
    $logger->app('api.auth.reject', [
        'ip'     => $loggedIp,
        'user'   => $actor->userId,
        'plan'   => $actor->plan,
        'reason' => 'plan',
    ], 'warning');

    ApiResponse::error(
        'The API is part of the subscription. This token is valid, but the account has no active one.',
        403,
        code: 'plan_required'
    );
}

// ── Convert ──────────────────────────────────────────────────────────────────
require BASE_PATH . '/src/Convert/bootstrap.php';

$outcome = $pipeline->handle(
    $_FILES,
    $_POST,
    $actor,
    (string) ($_SERVER['HTTP_USER_AGENT'] ?? ''),
);

Gc::registerShutdownSweep($config, $logger, $database, $files, $mappings);

// Read after the pipeline, so the numbers describe the caller's window as this
// request leaves it.
sendRateLimitHeaders($rateLimiters->make($quota->maxRequests, $quota->windowSeconds)->snapshot($quota->key));

foreach ($outcome->headers as $name => $value) {
    header("$name: $value");
}

$outcome->isSuccess()
    ? ApiResponse::success($outcome->payload, $outcome->status)
    : ApiResponse::error((string) $outcome->message, $outcome->status, $outcome->errors);

function sendRateLimitHeaders(RateLimitState $state): void
{
    header('X-RateLimit-Limit: ' . $state->limit);
    header('X-RateLimit-Remaining: ' . $state->remaining());
    header('X-RateLimit-Reset: ' . $state->resetAt);
}

// ── Docs renderer ────────────────────────────────────────────────────────────
function renderDocs(?\Data2Md\Auth\Session $session, \Data2Md\Auth\Tier $apiTier): void
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
    $base   = htmlspecialchars($base, ENT_QUOTES);
    $sample = ApiTokenStore::PREFIX . '4f1c8ab2d3e07695' . str_repeat('0', 28) . 'beef';
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

<?php
$navLinks = [
    ['href' => '/',    'label' => t('nav.about'), 'hideSm' => true],
    ['href' => '/api', 'label' => t('nav.api')],
];
$navCta = ['href' => '/app', 'label' => t('nav.app'), 'icon' => 'arrow'];
require BASE_PATH . '/src/View/header.php';
?>

<main class="l-main">
    <div class="l-container">
        <div class="docs-main">
            <article class="docs">

                <header class="docs-hero">
                    <span class="docs-eyebrow"><?= t('api.eyebrow') ?></span>
                    <h1 class="docs-title"><?= t('api.heading') ?></h1>
                    <p class="docs-sub"><?= t('api.lead', htmlspecialchars(APP_NAME), $apiTier->maxFileSizeLabel()) ?></p>
                </header>

                <section class="docs-section">
                    <h2><?= t('api.section.auth') ?></h2>
                    <p><?= t('api.auth.body') ?></p>
                    <div class="code-block-wrap">
                        <button type="button" class="code-copy" data-copy><?= t('api.copy') ?></button>
<pre class="code-block">Authorization: Bearer <?= $sample ?></pre>
                    </div>
                    <p><?= t('api.auth.rotate') ?></p>
                </section>

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
                                <tr><td><code>file</code></td><td>file</td><td><?= t('api.field.required.yes') ?></td><td><?= t('api.field.file.desc', $apiTier->maxFileSizeLabel()) ?></td></tr>
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
  <span class="ck">"message"</span>: <span class="cs">"The API is part of the subscription."</span>,
  <span class="ck">"code"</span>: <span class="cs">"plan_required"</span>
}</pre>
                    </div>
                    <p><?= t('api.error.note') ?></p>
                    <div class="docs-table-wrap">
                        <table class="docs-table">
                            <thead><tr><th><?= t('api.errors.col.status') ?></th><th><?= t('api.errors.col.code') ?></th><th><?= t('api.errors.col.meaning') ?></th></tr></thead>
                            <tbody>
                                <tr><td>401</td><td><code>token_required</code></td><td><?= t('api.error.token_required') ?></td></tr>
                                <tr><td>401</td><td><code>token_invalid</code></td><td><?= t('api.error.token_invalid') ?></td></tr>
                                <tr><td>403</td><td><code>plan_required</code></td><td><?= t('api.error.plan_required') ?></td></tr>
                                <tr><td>422</td><td>&mdash;</td><td><?= t('api.error.unprocessable') ?></td></tr>
                                <tr><td>429</td><td>&mdash;</td><td><?= t('api.error.throttled') ?></td></tr>
                                <tr><td>503</td><td>&mdash;</td><td><?= t('api.error.unavailable') ?></td></tr>
                            </tbody>
                        </table>
                    </div>
                </section>

                <section class="docs-section">
                    <h2><?= t('api.section.rate') ?></h2>
                    <p><?= t('api.rate.body', $apiTier->maxConversions, $apiTier->windowSeconds) ?></p>
                    <p><?= t('api.rate.headers') ?></p>
                </section>

                <section class="docs-section">
                    <h2><?= t('api.section.me') ?></h2>
                    <div class="endpoint-pill">
                        <span class="method">GET</span>
                        <code class="endpoint-url"><?= $base ?>/api/me</code>
                    </div>
                    <p><?= t('api.me.body') ?></p>
                    <div class="code-block-wrap">
                        <button type="button" class="code-copy" data-copy><?= t('api.copy') ?></button>
<pre class="code-block">{
  <span class="ck">"success"</span>: <span class="cn">true</span>,
  <span class="ck">"email"</span>: <span class="cs">"you@example.com"</span>,
  <span class="ck">"plan"</span>: <span class="cs">"premium"</span>,
  <span class="ck">"api_access"</span>: <span class="cn">true</span>,
  <span class="ck">"limits"</span>: {
    <span class="ck">"max_conversions"</span>: <span class="cn"><?= $apiTier->maxConversions ?></span>,
    <span class="ck">"window_seconds"</span>: <span class="cn"><?= $apiTier->windowSeconds ?></span>,
    <span class="ck">"max_file_size_bytes"</span>: <span class="cn"><?= $apiTier->maxFileSizeBytes() ?></span>
  },
  <span class="ck">"rate_limit"</span>: { <span class="ck">"limit"</span>: <span class="cn"><?= $apiTier->maxConversions ?></span>, <span class="ck">"remaining"</span>: <span class="cn">0</span>, <span class="ck">"reset"</span>: <span class="cn">1767225600</span> },
  <span class="ck">"subscription"</span>: { <span class="ck">"status"</span>: <span class="cs">"active"</span>, <span class="ck">"current_period_end"</span>: <span class="cn">1767225600</span>, <span class="ck">"cancel_at_period_end"</span>: <span class="cn">false</span> },
  <span class="ck">"token"</span>: { <span class="ck">"prefix"</span>: <span class="cs">"d2md_4f1c"</span>, <span class="ck">"last4"</span>: <span class="cs">"beef"</span>, <span class="ck">"created_at"</span>: <span class="cn">1764547200</span>, <span class="ck">"rotated_at"</span>: <span class="cn">null</span>, <span class="ck">"last_used_at"</span>: <span class="cn">1767139200</span> }
}</pre>
                    </div>
                    <p><?= t('api.me.note') ?></p>
                </section>

                <section class="docs-section">
                    <h2><?= t('api.section.examples') ?></h2>

                    <h3>cURL</h3>
                    <div class="code-block-wrap">
                        <button type="button" class="code-copy" data-copy><?= t('api.copy') ?></button>
<pre class="code-block">curl -X POST <?= $base ?>/api \
  -H <span class="cs">"Authorization: Bearer $D2MD_TOKEN"</span> \
  -F <span class="cs">"file=@document.pdf"</span> \
  -F <span class="cs">"format=pdf"</span>

curl <?= $base ?>/api/me \
  -H <span class="cs">"Authorization: Bearer $D2MD_TOKEN"</span></pre>
                    </div>

                    <h3>PHP</h3>
                    <div class="code-block-wrap">
                        <button type="button" class="code-copy" data-copy><?= t('api.copy') ?></button>
<pre class="code-block"><span class="cm">// stream a file via cURL</span>
$ch = curl_init(<span class="cs">'<?= $base ?>/api'</span>);
curl_setopt_array($ch, [
    CURLOPT_POST           =&gt; <span class="cn">true</span>,
    CURLOPT_RETURNTRANSFER =&gt; <span class="cn">true</span>,
    CURLOPT_HTTPHEADER     =&gt; [<span class="cs">'Authorization: Bearer '</span> . getenv(<span class="cs">'D2MD_TOKEN'</span>)],
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
<pre class="code-block"><span class="ck">import</span> os, requests

headers = {<span class="cs">'Authorization'</span>: <span class="cs">'Bearer '</span> + os.environ[<span class="cs">'D2MD_TOKEN'</span>]}

<span class="ck">with</span> <span class="ck">open</span>(<span class="cs">'document.pdf'</span>, <span class="cs">'rb'</span>) <span class="ck">as</span> f:
    r = requests.post(<span class="cs">'<?= $base ?>/api'</span>, headers=headers, files={<span class="cs">'file'</span>: f}, data={<span class="cs">'format'</span>: <span class="cs">'pdf'</span>})

data = r.json()
markdown = requests.get(<span class="cs">'<?= $base ?>'</span> + data[<span class="cs">'download_url'</span>]).text
<span class="ck">print</span>(markdown)</pre>
                    </div>
                </section>

            </article>
        </div>
    </div>
</main>

<?php
$footerText  = t('footer.api.text', e(APP_NAME));
$footerLinks = [
    ['href' => '/',        'label' => t('footer.home')],
    ['href' => '/app',     'label' => t('footer.converter')],
    ['href' => '/terms',   'label' => t('nav.terms')],
    ['href' => '/privacy', 'label' => t('nav.privacy')],
];
require BASE_PATH . '/src/View/footer.php';
?>

<script>
(function(){
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
