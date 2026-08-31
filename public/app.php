<?php

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));

/** @var \Data2Md\Config\Config $config */
$config = require BASE_PATH . '/src/bootstrap.php';

require BASE_PATH . '/src/I18n/bootstrap.php';
require BASE_PATH . '/src/Auth/bootstrap.php';

use Data2Md\I18n\I18n;

// X-Content-Type-Options, X-Frame-Options and Referrer-Policy come from the vhost.
header('X-XSS-Protection: 1; mode=block');
header("Content-Security-Policy: default-src 'self'; style-src 'self' 'unsafe-inline'; script-src 'self' 'unsafe-inline'; img-src 'self' data:");

$back = htmlspecialchars(I18n::sanitizeBack($_SERVER['REQUEST_URI'] ?? '/app'), ENT_QUOTES);
?>
<!DOCTYPE html>
<html lang="<?= I18n::current() ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php
    $metaTitle = t('app.title', APP_NAME);
    $metaDesc  = t('app.meta.desc');
    require BASE_PATH . '/src/View/head-meta.php';
    ?>
    <link rel="stylesheet" href="/assets/css/landing.css">
    <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body>

<!-- ── Header (shared with landing) ─────────────────────── -->
<?php
$navLinks = [
    ['href' => '/',    'label' => t('nav.about'), 'hideSm' => true],
    ['href' => '/api', 'label' => t('nav.api')],
];
// No CTA: this page is the converter, and the sign-in or dashboard entry comes
// from the header.
require BASE_PATH . '/src/View/header.php';
?>

<main class="l-main">
    <div class="l-container">
        <div class="app-shell">

            <!-- Errors (above upload) -->
            <div id="error-section" class="app-stack-item" hidden>
                <div class="alert alert-error" role="alert">
                    <ul id="error-list"></ul>
                </div>
            </div>

            <!-- Upload (centered, viewport-fit while idle) -->
            <section id="upload-section" class="app-stack-item app-upload">
                <div class="app-card">
                    <span class="app-eyebrow"><?= t('app.eyebrow') ?></span>
                    <h1 class="app-title"><?= t('app.heading') ?></h1>

                    <form id="upload-form" enctype="multipart/form-data" novalidate>
                        <input type="hidden" name="format" id="format-input" value="pdf">

                        <div class="drop-zone" id="drop-zone" tabindex="0" role="button" aria-label="<?= htmlspecialchars(t('app.dz.idle.title'), ENT_QUOTES) ?>">
                            <div id="dz-idle">
                                <div class="dz-icon" aria-hidden="true">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="38" height="38" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                                </div>
                                <p class="dz-title"><?= t('app.dz.idle.title') ?></p>
                                <p class="dz-sub"><?= t('app.dz.idle.sub', '<label for="file-input" class="browse-link">' . htmlspecialchars(t('app.dz.idle.browse')) . '</label>') ?></p>
                                <p class="dz-hint"><?= t('app.dz.idle.hint', MAX_FILE_SIZE_LABEL) ?></p>
                            </div>
                            <div id="dz-selected" hidden>
                                <div class="dz-icon dz-icon--ok" aria-hidden="true">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="38" height="38" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><polyline points="9 13 11 15 15 11"/></svg>
                                </div>
                                <p class="dz-title" id="dz-filename">document.pdf</p>
                                <p class="dz-sub"><?= t('app.dz.sel.sub', '<button type="button" class="browse-link" id="dz-change">' . htmlspecialchars(t('app.dz.sel.change')) . '</button>') ?></p>
                                <span class="dz-size" id="dz-size"></span>
                            </div>
                            <input type="file" name="file" id="file-input" accept=".pdf,application/pdf" class="sr-only">
                        </div>

                        <div class="format-row">
                            <span class="format-label"><?= t('app.format.label') ?></span>
                            <div class="format-pills" role="group" aria-label="<?= htmlspecialchars(t('app.format.label'), ENT_QUOTES) ?>">
                                <button type="button" class="pill pill--active" data-format="pdf">PDF</button>
                            </div>
                        </div>

                        <button type="submit" class="btn btn--primary btn--block" id="convert-btn" disabled>
                            <?= t('app.cta') ?>
                            <svg class="btn-arrow" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
                        </button>
                    </form>
                </div>

                <p class="app-helper">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                    <?= t('app.helper.anon') ?>
                    <span class="app-helper-sep" aria-hidden="true"></span>
                    <?= t('app.helper.streamed') ?>
                    <span class="app-helper-sep" aria-hidden="true"></span>
                    <?= t('app.helper.maxsize', MAX_FILE_SIZE_LABEL) ?>
                </p>
            </section>

            <!-- Result (revealed after conversion) -->
            <section id="result-section" class="app-stack-item" hidden>
                <div class="result-card">
                    <div class="result-header">
                        <div class="result-meta">
                            <span class="badge badge--ok"><?= t('app.result.badge') ?></span>
                            <span class="result-hash">
                                <span class="result-hash-label"><?= t('app.result.idlbl') ?></span>
                                <code id="res-hash"></code>
                            </span>
                        </div>
                        <div class="result-actions">
                            <button class="btn btn--ghost" id="copy-btn" type="button"><?= t('app.result.copy') ?></button>
                            <a href="#" class="btn btn--primary" id="res-download"><?= t('app.result.download') ?></a>
                        </div>
                    </div>
                    <div class="preview">
                        <div class="preview-bar">
                            <span class="preview-bar-label"><?= t('app.result.preview.label') ?></span>
                            <span class="preview-bar-info" id="res-chars"></span>
                        </div>
                        <pre class="preview-body" id="md-output"></pre>
                        <div class="preview-truncated" id="preview-truncated" hidden>
                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                            <span id="preview-truncated-msg"></span>
                        </div>
                    </div>
                    <div class="result-footer">
                        <button type="button" class="btn btn--ghost" id="btn-convert-another"><?= t('app.result.another') ?></button>
                    </div>
                </div>
            </section>

        </div>
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

<!-- ── Loading overlay ─────────────────────────────────── -->
<div id="loading-overlay" class="loading-overlay" hidden>
    <div class="loading-card">
        <div class="loading-spinner" aria-hidden="true"></div>
        <p class="loading-title">
            <?= t('app.loading.processing') ?> <strong id="loading-filename">&hellip;</strong>
            <span class="loading-size" id="loading-size"></span>
        </p>
        <p class="loading-status" id="loading-status"><?= t('app.loading.uploading') ?></p>
        <div class="loading-bar" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-label="<?= htmlspecialchars(t('app.loading.processing'), ENT_QUOTES) ?>">
            <div class="loading-bar-fill" id="loading-bar-fill"></div>
        </div>
        <div class="loading-tips">
            <p class="loading-tip" id="loading-tip"></p>
        </div>
    </div>
</div>

<script>
window.D2MD = {
    timeout: <?= \Data2Md\request_ceiling_seconds($config) * 1000 ?>,
    i18n: <?= json_encode(I18n::jsBundle(), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_HEX_APOS) ?>
};
</script>
<script src="/assets/js/app.js"></script>

</body>
</html>
