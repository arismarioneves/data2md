<?php

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
require BASE_PATH . '/config.php';

@ini_set('max_execution_time', (string) APP_MAX_EXECUTION_TIME);
@ini_set('memory_limit', APP_MEMORY_LIMIT);

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');
header("Content-Security-Policy: default-src 'self'; style-src 'self' 'unsafe-inline'; script-src 'self' 'unsafe-inline'; img-src 'self' data:");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>data2md — Convert</title>
    <link rel="stylesheet" href="/assets/css/landing.css">
    <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body>

<!-- ── Header (shared with landing) ─────────────────────── -->
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
                <a href="/#signin" class="l-nav-cta">
                    Sign in
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4M10 17l5-5-5-5M15 12H3"/></svg>
                </a>
            </nav>
        </div>
    </div>
</header>

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
                    <span class="app-eyebrow">// new conversion</span>
                    <h1 class="app-title">Drop a file. Get <em>Markdown</em>.</h1>

                    <form id="upload-form" enctype="multipart/form-data" novalidate>
                        <input type="hidden" name="format" id="format-input" value="pdf">

                        <div class="drop-zone" id="drop-zone" tabindex="0" role="button" aria-label="Upload file">
                            <div id="dz-idle">
                                <div class="dz-icon" aria-hidden="true">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="38" height="38" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                                </div>
                                <p class="dz-title">Drop your file here</p>
                                <p class="dz-sub">or <label for="file-input" class="browse-link">browse to upload</label></p>
                                <p class="dz-hint">PDF · MAX <?= MAX_FILE_SIZE_LABEL ?></p>
                            </div>
                            <div id="dz-selected" hidden>
                                <div class="dz-icon dz-icon--ok" aria-hidden="true">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="38" height="38" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><polyline points="9 13 11 15 15 11"/></svg>
                                </div>
                                <p class="dz-title" id="dz-filename">document.pdf</p>
                                <p class="dz-sub">Ready · <button type="button" class="browse-link" id="dz-change">change file</button></p>
                                <span class="dz-size" id="dz-size"></span>
                            </div>
                            <input type="file" name="file" id="file-input" accept=".pdf,application/pdf" class="sr-only">
                        </div>

                        <div class="format-row">
                            <span class="format-label">Format</span>
                            <div class="format-pills" role="group" aria-label="File format">
                                <button type="button" class="pill pill--active" data-format="pdf">PDF</button>
                            </div>
                        </div>

                        <button type="submit" class="btn btn--primary btn--block" id="convert-btn" disabled>
                            Convert to Markdown
                            <svg class="btn-arrow" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
                        </button>
                    </form>
                </div>

                <p class="app-helper">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                    Anonymous by design
                    <span class="app-helper-sep" aria-hidden="true"></span>
                    Streamed via pdftotext
                    <span class="app-helper-sep" aria-hidden="true"></span>
                    Up to <?= MAX_FILE_SIZE_LABEL ?>
                </p>
            </section>

            <!-- Result (revealed after conversion) -->
            <section id="result-section" class="app-stack-item" hidden>
                <div class="result-card">
                    <div class="result-header">
                        <div class="result-meta">
                            <span class="badge badge--ok">Converted</span>
                            <span class="result-hash">
                                <span class="result-hash-label">ID</span>
                                <code id="res-hash"></code>
                            </span>
                        </div>
                        <div class="result-actions">
                            <button class="btn btn--ghost" id="copy-btn" type="button">Copy Markdown</button>
                            <a href="#" class="btn btn--primary" id="res-download">Download .md</a>
                        </div>
                    </div>
                    <div class="preview">
                        <div class="preview-bar">
                            <span class="preview-bar-label">Markdown preview</span>
                            <span class="preview-bar-info" id="res-chars"></span>
                        </div>
                        <pre class="preview-body" id="md-output"></pre>
                        <div class="preview-truncated" id="preview-truncated" hidden>
                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                            <span id="preview-truncated-msg"></span>
                        </div>
                    </div>
                    <div class="result-footer">
                        <button type="button" class="btn btn--ghost" id="btn-convert-another">Convert another file</button>
                    </div>
                </div>
            </section>

        </div>
    </div>
</main>

<!-- ── Loading overlay ─────────────────────────────────── -->
<div id="loading-overlay" class="loading-overlay" hidden>
    <div class="loading-card">
        <div class="loading-spinner" aria-hidden="true"></div>
        <p class="loading-title">
            Processing <strong id="loading-filename">your file</strong>
            <span class="loading-size" id="loading-size"></span>
        </p>
        <p class="loading-status" id="loading-status">Uploading…</p>
        <div class="loading-bar" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-label="Progress">
            <div class="loading-bar-fill" id="loading-bar-fill"></div>
        </div>
        <div class="loading-tips">
            <p class="loading-tip" id="loading-tip"></p>
        </div>
    </div>
</div>

<script>var D2MD = { timeout: <?= (APP_MAX_EXECUTION_TIME + 60) * 1000 ?> };</script>
<script src="/assets/js/app.js"></script>

<script>
(function(){
    var header = document.getElementById('l-header');
    var onScroll = function(){
        if (window.scrollY > 8) header.classList.add('is-scrolled');
        else header.classList.remove('is-scrolled');
    };
    document.addEventListener('scroll', onScroll, { passive:true });
    onScroll();
})();
</script>

</body>
</html>
