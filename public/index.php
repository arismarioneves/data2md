<?php

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
require BASE_PATH . '/config.php';

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
    <title>data2md — Documents, distilled into Markdown</title>
    <meta name="description" content="Drop a PDF. Get clean, AI-ready Markdown. Built for large files, structure-preserving, and free to use.">
    <link rel="stylesheet" href="/assets/css/landing.css">
</head>
<body>

<!-- ── Header ──────────────────────────────────────────────── -->
<header class="l-header" id="l-header">
    <div class="l-container">
        <div class="l-header-inner">
            <a href="/" class="l-logo" aria-label="data2md home">
                <span class="l-logo-mark" aria-hidden="true"></span>
                <span class="l-logo-name">data2md</span>
            </a>
            <nav class="l-nav">
                <a href="#why" class="l-nav-link" data-hide-sm>Why Markdown</a>
                <a href="#performance" class="l-nav-link" data-hide-sm>Performance</a>
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

    <!-- ── Hero ────────────────────────────────────────────── -->
    <section class="l-hero">
        <div class="l-container">
            <span class="l-hero-eyebrow">
                <span class="l-hero-eyebrow-dot" aria-hidden="true"></span>
                Free · No account · Up to <?= MAX_FILE_SIZE_LABEL ?>
            </span>
            <h1>Documents, <em>distilled</em> into Markdown.</h1>
            <p class="l-hero-sub">
                Drop a PDF. Get clean, structured Markdown — ready to paste into ChatGPT, Claude, or any model.
                Large files welcome.
            </p>
            <div class="l-hero-cta">
                <a href="/app" class="l-btn l-btn--primary">
                    Convert a file
                    <svg class="l-btn-arrow" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
                </a>
                <a href="#signin" class="l-btn l-btn--ghost">
                    Sign in
                </a>
            </div>
            <div class="l-hero-meta">
                <span>
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="20 6 9 17 4 12"/></svg>
                    Anonymous by design
                </span>
                <span class="l-hero-meta-sep" aria-hidden="true"></span>
                <span>
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="20 6 9 17 4 12"/></svg>
                    Streamed via pdftotext
                </span>
                <span class="l-hero-meta-sep" aria-hidden="true"></span>
                <span>
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="20 6 9 17 4 12"/></svg>
                    REST API included
                </span>
            </div>
        </div>
    </section>

    <!-- ── Demo (PDF → MD) ────────────────────────────────── -->
    <section class="l-demo" aria-label="Demo">
        <div class="l-container">
            <div class="l-demo-frame">
                <div class="l-demo-grid">

                    <!-- PDF pane (visual mock) -->
                    <div class="l-demo-pane l-demo-pane--pdf">
                        <div class="l-demo-bar">
                            <span class="l-demo-bar-dots" aria-hidden="true"><i></i><i></i><i></i></span>
                            <span>report.pdf</span>
                        </div>
                        <div class="l-demo-body" aria-hidden="true">
                            <div class="l-pdf-h"></div>
                            <div class="l-pdf-line w95"></div>
                            <div class="l-pdf-line w92"></div>
                            <div class="l-pdf-line w88"></div>
                            <div class="l-pdf-line w70"></div>
                            <div class="l-pdf-h2"></div>
                            <div class="l-pdf-line w98"></div>
                            <div class="l-pdf-line w85"></div>
                            <div class="l-pdf-line w92"></div>
                            <div class="l-pdf-line w78"></div>
                            <div class="l-pdf-line w50"></div>
                        </div>
                    </div>

                    <!-- Arrow -->
                    <div class="l-demo-arrow" aria-hidden="true">
                        <span class="l-demo-arrow-dot">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
                        </span>
                    </div>

                    <!-- Markdown pane -->
                    <div class="l-demo-pane l-demo-pane--md">
                        <div class="l-demo-bar">
                            <span class="l-demo-bar-dots" aria-hidden="true"><i></i><i></i><i></i></span>
                            <span>report.md</span>
                        </div>
                        <div class="l-demo-body">
<pre class="l-md-body"><span class="l-md-h"># Quarterly Report</span>

Revenue grew <span class="l-md-em">**27%</span> year over year,
driven by enterprise contracts in EMEA.

<span class="l-md-h">## Highlights</span>

<span class="l-md-mu">-</span> ARR crossed <span class="l-md-str">"$12M"</span>
<span class="l-md-mu">-</span> 4 new strategic partnerships
<span class="l-md-mu">-</span> Net retention at <span class="l-md-em">**118%**</span></pre>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </section>

    <!-- ── Why Markdown ──────────────────────────────────── -->
    <section class="l-section" id="why">
        <div class="l-container">
            <span class="l-eyebrow">// why markdown</span>
            <h2 class="l-h2">Plain text the way <em>machines</em> were meant to read it.</h2>
            <p class="l-lead">
                Markdown is the lingua franca of modern AI tools, version control and documentation.
                Convert once — use everywhere.
            </p>

            <div class="l-why-grid">

                <div class="l-why-card">
                    <div class="l-why-icon" aria-hidden="true">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2 9 9l-7 1 5 5-1.5 7L12 18l6.5 4L17 15l5-5-7-1z"/></svg>
                    </div>
                    <h3 class="l-why-title">Built for AI models.</h3>
                    <p class="l-why-desc">
                        LLMs understand Markdown natively. Structured text uses fewer tokens, fits larger context windows,
                        and produces measurably more accurate answers than raw binary.
                    </p>
                    <span class="l-why-tag">~30% fewer tokens vs raw PDF text</span>
                </div>

                <div class="l-why-card">
                    <div class="l-why-icon" aria-hidden="true">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>
                    </div>
                    <h3 class="l-why-title">Structure preserved.</h3>
                    <p class="l-why-desc">
                        Pages, headings and paragraphs survive the conversion. Your document keeps its logical flow —
                        readable by humans and machines alike, without binary noise or encoding artefacts.
                    </p>
                    <span class="l-why-tag">page-aware extraction</span>
                </div>

                <div class="l-why-card">
                    <div class="l-why-icon" aria-hidden="true">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>
                    </div>
                    <h3 class="l-why-title">Universal format.</h3>
                    <p class="l-why-desc">
                        Markdown opens in every text editor. It renders natively in GitHub, Notion, Obsidian and
                        thousands of tools. Store it in Git and get meaningful line-by-line diffs forever.
                    </p>
                    <span class="l-why-tag">.md · everywhere</span>
                </div>

                <div class="l-why-card">
                    <div class="l-why-icon" aria-hidden="true">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><line x1="8.59" y1="13.51" x2="15.42" y2="17.49"/><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"/></svg>
                    </div>
                    <h3 class="l-why-title">Diff-friendly forever.</h3>
                    <p class="l-why-desc">
                        Track every change at the line level. Review document edits in a pull request like you would
                        review code. No more comparing PDF revisions side by side.
                    </p>
                    <span class="l-why-tag">git-native workflows</span>
                </div>

            </div>
        </div>
    </section>

    <!-- ── Performance / Big files ──────────────────────────── -->
    <section class="l-section l-perf" id="performance">
        <div class="l-container">
            <div class="l-perf-grid">

                <div>
                    <span class="l-eyebrow">// big files welcome</span>
                    <h2 class="l-h2">A <em>500&nbsp;MB</em> PDF doesn't scare us.</h2>
                    <p class="l-lead">
                        Streaming conversion via <code>pdftotext</code> means constant memory regardless of file size.
                        The browser never freezes — upload progress and server-side extraction are tracked in real time,
                        with friendly tips while you wait.
                    </p>

                    <div class="l-perf-stat-row">
                        <div>
                            <span class="l-stat-num"><em><?= MAX_FILE_SIZE_LABEL ?></em></span>
                            <span class="l-stat-lbl">max upload</span>
                        </div>
                        <div>
                            <span class="l-stat-num">~<em>O(1)</em></span>
                            <span class="l-stat-lbl">memory footprint</span>
                        </div>
                        <div>
                            <span class="l-stat-num"><em>0</em></span>
                            <span class="l-stat-lbl">UI freezes</span>
                        </div>
                    </div>
                </div>

                <div class="l-perf-card" aria-hidden="true">
                    <div class="l-perf-card-bar">
                        <span>Conversion benchmarks</span>
                        <span>pdftotext · streamed</span>
                    </div>

                    <div class="l-perf-row">
                        <span class="l-perf-row-name">whitepaper.pdf <span class="l-pill">2 MB</span></span>
                        <span class="l-perf-bar"><span class="l-perf-bar-fill" style="--w:14%"></span></span>
                        <span class="l-perf-row-time">0.4 s</span>
                    </div>
                    <div class="l-perf-row">
                        <span class="l-perf-row-name">contract.pdf <span class="l-pill">12 MB</span></span>
                        <span class="l-perf-bar"><span class="l-perf-bar-fill" style="--w:26%"></span></span>
                        <span class="l-perf-row-time">1.2 s</span>
                    </div>
                    <div class="l-perf-row">
                        <span class="l-perf-row-name">manual.pdf <span class="l-pill">85 MB</span></span>
                        <span class="l-perf-bar"><span class="l-perf-bar-fill" style="--w:55%"></span></span>
                        <span class="l-perf-row-time">4.8 s</span>
                    </div>
                    <div class="l-perf-row">
                        <span class="l-perf-row-name">archive.pdf <span class="l-pill">320 MB</span></span>
                        <span class="l-perf-bar"><span class="l-perf-bar-fill" style="--w:82%"></span></span>
                        <span class="l-perf-row-time">18.6 s</span>
                    </div>
                    <div class="l-perf-row">
                        <span class="l-perf-row-name">corpus.pdf <span class="l-pill">500 MB</span></span>
                        <span class="l-perf-bar"><span class="l-perf-bar-fill" style="--w:100%"></span></span>
                        <span class="l-perf-row-time">29.4 s</span>
                    </div>
                </div>

            </div>
        </div>
    </section>

    <!-- ── Sign-in section ───────────────────────────────── -->
    <section class="l-section l-signin" id="signin">
        <div class="l-container">
            <span class="l-eyebrow">// coming soon</span>
            <h2 class="l-h2" style="margin-inline:auto;text-align:center">Save your <em>conversions</em>.</h2>
            <p class="l-lead" style="margin-inline:auto;text-align:center">
                Sign in to keep a private library of your converted documents,
                share download links and access them from anywhere.
            </p>

            <div class="l-signin-card">
                <div class="l-signin-icon" aria-hidden="true">
                    <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 11h-6M19 8v6"/></svg>
                </div>
                <h3>Create your library</h3>
                <p>One click with Google. We'll never share your data — files remain anonymous.</p>

                <button type="button" class="l-btn l-btn--google l-google-btn" disabled aria-disabled="true" title="Google sign-in is coming soon">
                    <svg width="18" height="18" viewBox="0 0 18 18" aria-hidden="true">
                        <path fill="#4285F4" d="M17.64 9.2c0-.637-.057-1.251-.164-1.84H9v3.481h4.844a4.14 4.14 0 0 1-1.796 2.716v2.259h2.908c1.702-1.567 2.684-3.875 2.684-6.615z"/>
                        <path fill="#34A853" d="M9 18c2.43 0 4.467-.806 5.956-2.184l-2.908-2.259c-.806.54-1.837.86-3.048.86-2.344 0-4.328-1.584-5.036-3.711H.957v2.332A8.997 8.997 0 0 0 9 18z"/>
                        <path fill="#FBBC05" d="M3.964 10.706A5.41 5.41 0 0 1 3.682 9c0-.593.102-1.17.282-1.706V4.962H.957A8.997 8.997 0 0 0 0 9c0 1.452.348 2.827.957 4.038l3.007-2.332z"/>
                        <path fill="#EA4335" d="M9 3.58c1.321 0 2.508.454 3.44 1.345l2.582-2.58C13.463.891 11.426 0 9 0A8.997 8.997 0 0 0 .957 4.962L3.964 7.294C4.672 5.167 6.656 3.58 9 3.58z"/>
                    </svg>
                    Continue with Google
                </button>

                <div class="l-soon">
                    <span class="l-soon-dot" aria-hidden="true"></span>
                    Authentication arriving soon
                </div>

                <div class="l-signin-or">or</div>

                <a href="/app" class="l-btn l-btn--ghost l-google-btn" style="display:flex">
                    Continue without an account
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
                </a>
            </div>
        </div>
    </section>

</main>

<!-- ── Footer ─────────────────────────────────────────────── -->
<footer class="l-footer">
    <div class="l-container">
        <div class="l-footer-inner">
            <p class="l-footer-text">
                <strong>data2md</strong> — files are stored under anonymous identifiers.
            </p>
            <nav class="l-footer-links">
                <a href="/app">App</a>
                <a href="/api">API</a>
                <a href="https://daringfireball.net/projects/markdown/" target="_blank" rel="noreferrer">About .md</a>
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
})();
</script>

</body>
</html>
