<?php

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));

/** @var \Data2Md\Config\Config $config */
$config = require BASE_PATH . '/src/bootstrap.php';
require BASE_PATH . '/src/I18n/bootstrap.php';
require BASE_PATH . '/src/Auth/bootstrap.php';

use Data2Md\Auth\ApiTokenStore;
use Data2Md\Auth\Session;
use Data2Md\I18n\I18n;
use Data2Md\Log\Logger;
use Data2Md\Storage\Database;
use Data2Md\Storage\FileStorage;
use Data2Md\Storage\MappingStorage;

/** @var Session|null $session */
if ($session === null) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Not found.\n";
    exit;
}

if (!$session->isAuthenticated()) {
    header('Location: /login', true, 303);
    exit;
}

const PER_PAGE = 20;

$dataDir   = $config->string('APP_DATA_DIR');
$logger    = new Logger($dataDir . '/logs');
$database  = new Database($dataDir . '/db/mappings.sqlite');
$mappings  = new MappingStorage($database);
$files     = new FileStorage($dataDir . '/md', $dataDir . '/tmp');
$apiTokens = new ApiTokenStore($database);
$userId    = (int) $session->userId();

$notice = null;
$error  = null;
// The raw token exists for this one response and is never stored, so the
// token actions render in place instead of redirecting.
$freshToken = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$session->checkCsrf($_POST['csrf'] ?? null)) {
        http_response_code(403);
        header('Content-Type: text/plain; charset=utf-8');
        echo "Invalid request token.\n";
        exit;
    }

    if (($_POST['action'] ?? '') === 'delete') {
        $hash = (string) ($_POST['hash'] ?? '');

        // Ownership is in the statement, so a hash belonging to someone else or
        // to no account at all simply matches nothing.
        if ($mappings->deleteForUser($hash, $userId)) {
            $files->deleteMarkdown($hash);
            $logger->app('file.delete', ['hash' => $hash, 'user' => $userId], 'warning');
            $flash = 'deleted';
        } else {
            $logger->app('file.delete.reject', ['hash' => $hash, 'user' => $userId], 'warning');
            $flash = 'denied';
        }

        // Redirect after the write so a refresh cannot repeat it.
        header('Location: /dashboard?f=' . $flash . '&p=' . (int) ($_POST['page'] ?? 1), true, 303);
        exit;
    }

    if (($_POST['action'] ?? '') === 'token:create') {
        $freshToken = $apiTokens->issue($userId);
        $notice     = t('dash.api.issued');

        $logger->app('api.token.issue', ['user' => $userId], 'warning');
    }

    if (($_POST['action'] ?? '') === 'token:revoke') {
        $apiTokens->revokeFor($userId);
        $notice = t('dash.api.revoked');

        $logger->app('api.token.revoke', ['user' => $userId], 'warning');
    }
}

$tokenInfo = $apiTokens->describeFor($userId);
$flash     = (string) ($_GET['f'] ?? '');

if ($flash === 'deleted') {
    $notice = t('dash.deleted');
} elseif ($flash === 'denied') {
    $error = t('dash.delete.failed');
}

$total = $mappings->countForUser($userId);
$pages = max(1, (int) ceil($total / PER_PAGE));
$page  = min($pages, max(1, (int) ($_GET['p'] ?? 1)));
$rows  = $mappings->forUser($userId, PER_PAGE, ($page - 1) * PER_PAGE);

$user      = (string) $session->email();
$createdAt = (int) ($session->user()['created_at'] ?? time());
$dateFmt   = I18n::current() === 'pt' ? 'd/m/Y H:i' : 'Y-m-d H:i';

header('Cache-Control: private, no-store');
header('Vary: Cookie');
header('Content-Type: text/html; charset=utf-8');
header("Content-Security-Policy: default-src 'self'; style-src 'self' 'unsafe-inline'; script-src 'self' 'unsafe-inline'; img-src 'self' data:");

$metaTitle = t('dash.title', APP_NAME);
$metaDesc  = t('dash.meta.desc');
$csrf      = e((string) $session->csrfToken());
?>
<!DOCTYPE html>
<html lang="<?= I18n::current() ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    <?php require BASE_PATH . '/src/View/head-meta.php'; ?>
    <link rel="stylesheet" href="/assets/css/landing.css">
    <link rel="stylesheet" href="/assets/css/dashboard.css">
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

<main class="l-main dash-main">
    <div class="l-container">

        <header class="dash-hero">
            <span class="l-eyebrow"><?= t('dash.eyebrow') ?></span>
            <h1 class="dash-h1"><?= t('dash.heading') ?></h1>
        </header>

        <?php if ($notice !== null): ?>
        <p class="dash-flash" role="status"><?= e($notice) ?></p>
        <?php endif; ?>
        <?php if ($error !== null): ?>
        <p class="dash-flash dash-flash--error" role="alert"><?= e($error) ?></p>
        <?php endif; ?>

        <section class="dash-panel">
            <div class="dash-panel-head">
                <h2 class="dash-h2"><?= t('dash.account.head') ?></h2>
            </div>
            <div class="dash-account">
                <div>
                    <p class="dash-email"><?= e($user) ?></p>
                    <p class="dash-sub"><?= t('dash.account.since', gmdate($dateFmt, $createdAt)) ?></p>
                </div>
                <form method="post" action="/logout">
                    <input type="hidden" name="csrf" value="<?= $csrf ?>">
                    <button type="submit" class="l-btn l-btn--ghost dash-btn-sm"><?= t('nav.signout') ?></button>
                </form>
            </div>
        </section>

        <section class="dash-panel">
            <div class="dash-panel-head">
                <h2 class="dash-h2"><?= t('dash.api.head') ?></h2>
                <span class="dash-count"><?= t(
                    'dash.api.limits',
                    $viewerTier->maxConversions,
                    $viewerTier->windowSeconds,
                    $viewerTier->maxFileSizeLabel()
                ) ?></span>
            </div>

            <?php if ($freshToken !== null): ?>
            <div class="dash-token-fresh">
                <p class="dash-token-once"><?= t('dash.api.once') ?></p>
                <div class="dash-token-row">
                    <code class="dash-token-value"><?= e($freshToken) ?></code>
                    <button type="button" class="l-btn l-btn--primary dash-btn-sm" data-copy-value="<?= e($freshToken) ?>"><?= t('dash.api.copy') ?></button>
                </div>
            </div>
            <?php endif; ?>

            <?php if (!$viewerTier->allowsApi()): ?>
            <p class="dash-note"><?= t('dash.api.locked', '<code>GET /api/me</code>') ?></p>
            <?php endif; ?>

            <div class="dash-account">
                <?php if ($tokenInfo === null): ?>
                <div>
                    <p class="dash-email"><?= t('dash.api.none') ?></p>
                    <p class="dash-sub"><?= t('dash.api.none.sub') ?></p>
                </div>
                <form method="post" action="/dashboard">
                    <input type="hidden" name="csrf" value="<?= $csrf ?>">
                    <input type="hidden" name="action" value="token:create">
                    <button type="submit" class="l-btn l-btn--primary dash-btn-sm"><?= t('dash.api.create') ?></button>
                </form>
                <?php else: ?>
                <div>
                    <p class="dash-token-mask"><code><?= e($tokenInfo['prefix']) ?></code>&hellip;<code><?= e($tokenInfo['last4']) ?></code></p>
                    <p class="dash-sub">
                        <?= t('dash.api.created', gmdate($dateFmt, (int) $tokenInfo['created_at'])) ?>
                        &middot;
                        <?= $tokenInfo['last_used_at'] === null
                            ? t('dash.api.unused')
                            : t('dash.api.used', gmdate($dateFmt, (int) $tokenInfo['last_used_at'])) ?>
                    </p>
                </div>
                <div class="dash-actions">
                    <form method="post" action="/dashboard" class="dash-inline" data-confirm="<?= e(t('dash.api.rotate.confirm')) ?>">
                        <input type="hidden" name="csrf" value="<?= $csrf ?>">
                        <input type="hidden" name="action" value="token:create">
                        <button type="submit" class="l-btn l-btn--ghost dash-btn-sm"><?= t('dash.api.rotate') ?></button>
                    </form>
                    <form method="post" action="/dashboard" class="dash-inline" data-confirm="<?= e(t('dash.api.revoke.confirm')) ?>">
                        <input type="hidden" name="csrf" value="<?= $csrf ?>">
                        <input type="hidden" name="action" value="token:revoke">
                        <button type="submit" class="dash-link dash-link--danger"><?= t('dash.api.revoke') ?></button>
                    </form>
                </div>
                <?php endif; ?>
            </div>

            <p class="dash-sub dash-api-foot"><?= t('dash.api.docs', '<a class="dash-link" href="/api">/api</a>') ?></p>
        </section>

        <section class="dash-panel">
            <div class="dash-panel-head">
                <h2 class="dash-h2"><?= t('dash.files.head') ?></h2>
                <span class="dash-count"><?= t('dash.files.count', $total) ?></span>
            </div>

            <?php if ($rows === []): ?>
                <div class="dash-empty">
                    <div class="dash-empty-icon" aria-hidden="true">
                        <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/></svg>
                    </div>
                    <h3><?= t('dash.empty.head') ?></h3>
                    <p><?= t('dash.empty.body') ?></p>
                    <a class="l-btn l-btn--primary" href="/app"><?= t('dash.empty.cta') ?></a>
                </div>
            <?php else: ?>
                <p class="dash-note"><?= t('dash.files.note') ?></p>

                <div class="dash-table-wrap">
                    <table class="dash-table">
                        <thead>
                            <tr>
                                <th><?= t('dash.col.name') ?></th>
                                <th class="dash-num"><?= t('dash.col.size') ?></th>
                                <th><?= t('dash.col.date') ?></th>
                                <th><span class="dash-sr">·</span></th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($rows as $row): ?>
                            <tr>
                                <td class="dash-name" title="<?= e($row['original_name']) ?>"><?= e($row['original_name']) ?></td>
                                <td class="dash-num"><?= e(\Data2Md\format_bytes($row['size_bytes'])) ?></td>
                                <td class="dash-date"><?= e(gmdate($dateFmt, $row['created_at'])) ?></td>
                                <td class="dash-actions">
                                    <a class="dash-link" href="/download?hash=<?= e($row['hash']) ?>"><?= t('dash.download') ?></a>
                                    <button type="button" class="dash-link" data-copy-value="<?= e(rtrim(APP_URL, '/')) ?>/download?hash=<?= e($row['hash']) ?>"><?= t('dash.copy') ?></button>
                                    <form method="post" action="/dashboard" class="dash-inline"
                                          data-confirm="<?= e(t('dash.delete.confirm')) ?>">
                                        <input type="hidden" name="csrf" value="<?= $csrf ?>">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="hash" value="<?= e($row['hash']) ?>">
                                        <input type="hidden" name="page" value="<?= $page ?>">
                                        <button type="submit" class="dash-link dash-link--danger"><?= t('dash.delete') ?></button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($pages > 1): ?>
                <nav class="dash-pager">
                    <?php if ($page > 1): ?>
                    <a class="l-btn l-btn--ghost dash-btn-sm" href="/dashboard?p=<?= $page - 1 ?>"><?= t('dash.newer') ?></a>
                    <?php endif; ?>
                    <span class="dash-sub"><?= t('dash.page', $page, $pages) ?></span>
                    <?php if ($page < $pages): ?>
                    <a class="l-btn l-btn--ghost dash-btn-sm" href="/dashboard?p=<?= $page + 1 ?>"><?= t('dash.older') ?></a>
                    <?php endif; ?>
                </nav>
                <?php endif; ?>
            <?php endif; ?>
        </section>

    </div>
</main>

<?php
$footerText  = t('footer.text', e(APP_NAME));
$footerLinks = [
    ['href' => '/terms',   'label' => t('nav.terms')],
    ['href' => '/privacy', 'label' => t('nav.privacy')],
];
require BASE_PATH . '/src/View/footer.php';
?>

<script>
(function(){
    var copied = <?= json_encode(t('dash.copied'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_HEX_APOS) ?>;

    document.querySelectorAll('[data-copy-value]').forEach(function(btn){
        btn.addEventListener('click', function(){
            var url = btn.getAttribute('data-copy-value');
            var done = function(){
                var label = btn.textContent;
                btn.textContent = copied;
                btn.classList.add('is-copied');
                setTimeout(function(){
                    btn.textContent = label;
                    btn.classList.remove('is-copied');
                }, 1600);
            };
            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(url).then(done).catch(function(){ legacy(url, done); });
            } else {
                legacy(url, done);
            }
        });
    });

    document.querySelectorAll('form[data-confirm]').forEach(function(form){
        form.addEventListener('submit', function(event){
            if (!window.confirm(form.getAttribute('data-confirm'))) {
                event.preventDefault();
            }
        });
    });

    function legacy(text, cb){
        var ta = document.createElement('textarea');
        ta.value = text;
        ta.style.cssText = 'position:fixed;opacity:0;pointer-events:none';
        document.body.appendChild(ta);
        ta.focus(); ta.select();
        try { document.execCommand('copy'); cb(); } catch (_) {}
        document.body.removeChild(ta);
    }
})();
</script>

</body>
</html>
