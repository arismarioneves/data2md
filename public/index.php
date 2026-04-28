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
    <title>data2md — Convert Files to Markdown</title>
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
    <header class="header">
        <div class="container">
            <div class="header-inner">
                <a href="/" class="logo"><span class="logo-mark">d2md</span></a>
                <nav><a href="/api" class="nav-link">API</a></nav>
            </div>
        </div>
    </header>

    <main class="main">
        <div class="container">

            <!-- Hero -->
            <section class="hero">
                <h1 class="hero-title">Turn documents into <span class="accent">AI&#8209;ready&nbsp;Markdown</span></h1>
                <p class="hero-sub">Upload a PDF and get clean, structured Markdown in seconds — ready to paste straight into ChatGPT, Claude, or any AI model.</p>
                <div class="hero-badges">
                    <span class="hero-badge">No account needed</span>
                    <span class="hero-badge">Up to <?= MAX_FILE_SIZE_LABEL ?></span>
                    <span class="hero-badge">Anonymous by design</span>
                </div>
            </section>

            <!-- Why Markdown -->
            <div class="features">
                <div class="feature-card">
                    <div class="feature-icon-wrap" aria-hidden="true">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2L9.5 9.5 2 12l7.5 2.5L12 22l2.5-7.5L22 12l-7.5-2.5z"/></svg>
                    </div>
                    <h3 class="feature-title">Perfect for AI models</h3>
                    <p class="feature-desc">LLMs like ChatGPT, Claude and Gemini understand Markdown natively. Structured text uses fewer tokens, fits larger context windows and produces more accurate answers than raw binary.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon-wrap" aria-hidden="true">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>
                    </div>
                    <h3 class="feature-title">Structure preserved</h3>
                    <p class="feature-desc">Pages, headings and paragraphs survive the conversion. Your document keeps its logical flow — readable by both humans and machines, without binary noise or encoding artefacts.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon-wrap" aria-hidden="true">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>
                    </div>
                    <h3 class="feature-title">Universal format</h3>
                    <p class="feature-desc">Markdown opens in every text editor. It renders natively in GitHub, Notion and Obsidian. Store it in Git and get meaningful line-by-line diffs on every document change.</p>
                </div>
            </div>

            <!-- Errors (populated by JS) -->
            <div id="error-section" hidden>
                <div class="alert alert-error" role="alert">
                    <ul id="error-list"></ul>
                </div>
            </div>

            <!-- Upload -->
            <div id="upload-section">
                <div class="card">
                    <form id="upload-form" enctype="multipart/form-data" novalidate>
                        <input type="hidden" name="format" id="format-input" value="pdf">

                        <div class="drop-zone" id="drop-zone" tabindex="0" role="button" aria-label="Upload file">
                            <div id="dz-idle">
                                <div class="dz-icon" aria-hidden="true">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="12" y1="18" x2="12" y2="12"/><line x1="9" y1="15" x2="15" y2="15"/></svg>
                                </div>
                                <p class="dz-title">Drop your file here</p>
                                <p class="dz-sub">or <label for="file-input" class="browse-link">browse to upload</label></p>
                                <p class="dz-hint">PDF &bull; Max <?= MAX_FILE_SIZE_LABEL ?></p>
                            </div>
                            <div id="dz-selected" hidden>
                                <div class="dz-icon dz-icon--ok" aria-hidden="true">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><polyline points="9 12 11 14 15 10"/></svg>
                                </div>
                                <p class="dz-title" id="dz-filename">document.pdf</p>
                                <p class="dz-sub">Ready to convert &mdash; <button type="button" class="browse-link" id="dz-change">change file</button></p>
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
                        </button>
                    </form>
                </div>
            </div>

            <!-- Result (hidden; populated by JS after conversion) -->
            <div id="result-section" hidden>
                <div class="card card--result">
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
                        <button type="button" class="btn btn--ghost" id="btn-convert-another">Convert Another File</button>
                    </div>
                </div>
            </div>

        </div>
    </main>

    <footer class="footer">
        <div class="container">
            <p>data2md &mdash; Files are stored with anonymous identifiers. Your data is never publicly linked to your identity.</p>
        </div>
    </footer>

    <!-- Loading overlay (shown on submit, updated with real progress) -->
    <div id="loading-overlay" hidden>
        <div class="loading-card">
            <div class="loading-spinner" aria-hidden="true"></div>
            <p class="loading-title">Processing <strong id="loading-filename">your file</strong></p>
            <p class="loading-status" id="loading-status">Uploading&hellip;</p>
            <div class="loading-bar" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-label="Progress">
                <div class="loading-bar-fill" id="loading-bar-fill"></div>
            </div>
            <div class="loading-tips">
                <p class="loading-tip" id="loading-tip"></p>
            </div>
        </div>
    </div>

    <!-- Pass PHP runtime config to JS -->
    <script>var D2MD = { timeout: <?= (APP_MAX_EXECUTION_TIME + 60) * 1000 ?> };</script>
    <script src="/assets/js/app.js"></script>
</body>
</html>
