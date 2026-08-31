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

$back = htmlspecialchars(I18n::sanitizeBack($_SERVER['REQUEST_URI'] ?? '/'), ENT_QUOTES);
?>
<!DOCTYPE html>
<html lang="<?= I18n::current() ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php
    $metaTitle = t('landing.title', APP_NAME);
    $metaDesc  = t('landing.meta.desc');
    require BASE_PATH . '/src/View/head-meta.php';
    ?>
    <link rel="stylesheet" href="/assets/css/landing.css">
</head>
<body>

<!-- ── Header ──────────────────────────────────────────────── -->
<?php
$navLinks = [
    ['href' => '#why',          'label' => t('nav.why'),         'hideSm' => true],
    ['href' => '#performance',  'label' => t('nav.performance'), 'hideSm' => true],
    ['href' => '/api',          'label' => t('nav.api')],
];
$navCta = ['href' => '/app', 'label' => t('nav.app'), 'icon' => 'arrow'];
require BASE_PATH . '/src/View/header.php';
?>

<main class="l-main">

    <!-- ── Hero ────────────────────────────────────────────── -->
    <section class="l-hero">
        <div class="l-container">
            <span class="l-hero-eyebrow">
                <span class="l-hero-eyebrow-dot" aria-hidden="true"></span>
                <?= t('landing.hero.eyebrow', MAX_FILE_SIZE_LABEL) ?>
            </span>
            <h1><?= t('landing.hero.title') ?></h1>
            <p class="l-hero-sub"><?= t('landing.hero.sub') ?></p>
            <div class="l-hero-cta">
                <a href="/app" class="l-btn l-btn--primary">
                    <?= t('landing.hero.cta') ?>
                    <svg class="l-btn-arrow" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
                </a>
                <a href="#signin" class="l-btn l-btn--ghost">
                    <?= t('landing.hero.cta2') ?>
                </a>
            </div>
            <div class="l-hero-meta">
                <span>
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="20 6 9 17 4 12"/></svg>
                    <?= t('landing.hero.meta1') ?>
                </span>
                <span class="l-hero-meta-sep" aria-hidden="true"></span>
                <span>
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="20 6 9 17 4 12"/></svg>
                    <?= t('landing.hero.meta2') ?>
                </span>
                <span class="l-hero-meta-sep" aria-hidden="true"></span>
                <span>
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="20 6 9 17 4 12"/></svg>
                    <?= t('landing.hero.meta3') ?>
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
            <span class="l-eyebrow"><?= t('landing.why.eyebrow') ?></span>
            <h2 class="l-h2"><?= t('landing.why.title') ?></h2>
            <p class="l-lead"><?= t('landing.why.lead') ?></p>

            <div class="l-why-grid">

                <div class="l-why-card">
                    <div class="l-why-icon" aria-hidden="true">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2 9 9l-7 1 5 5-1.5 7L12 18l6.5 4L17 15l5-5-7-1z"/></svg>
                    </div>
                    <h3 class="l-why-title"><?= t('landing.why.card1.title') ?></h3>
                    <p class="l-why-desc"><?= t('landing.why.card1.desc') ?></p>
                    <span class="l-why-tag"><?= t('landing.why.card1.tag') ?></span>
                </div>

                <div class="l-why-card">
                    <div class="l-why-icon" aria-hidden="true">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>
                    </div>
                    <h3 class="l-why-title"><?= t('landing.why.card2.title') ?></h3>
                    <p class="l-why-desc"><?= t('landing.why.card2.desc') ?></p>
                    <span class="l-why-tag"><?= t('landing.why.card2.tag') ?></span>
                </div>

                <div class="l-why-card">
                    <div class="l-why-icon" aria-hidden="true">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>
                    </div>
                    <h3 class="l-why-title"><?= t('landing.why.card3.title') ?></h3>
                    <p class="l-why-desc"><?= t('landing.why.card3.desc') ?></p>
                    <span class="l-why-tag"><?= t('landing.why.card3.tag') ?></span>
                </div>

                <div class="l-why-card">
                    <div class="l-why-icon" aria-hidden="true">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><line x1="8.59" y1="13.51" x2="15.42" y2="17.49"/><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"/></svg>
                    </div>
                    <h3 class="l-why-title"><?= t('landing.why.card4.title') ?></h3>
                    <p class="l-why-desc"><?= t('landing.why.card4.desc') ?></p>
                    <span class="l-why-tag"><?= t('landing.why.card4.tag') ?></span>
                </div>

            </div>
        </div>
    </section>

    <!-- ── Performance / Big files ──────────────────────────── -->
    <section class="l-section l-perf" id="performance">
        <div class="l-container">
            <div class="l-perf-grid">

                <div>
                    <span class="l-eyebrow"><?= t('landing.perf.eyebrow') ?></span>
                    <h2 class="l-h2"><?= t('landing.perf.title', str_replace(' ', "\u{00A0}", MAX_FILE_SIZE_LABEL)) ?></h2>
                    <p class="l-lead"><?= t('landing.perf.lead') ?></p>

                    <div class="l-perf-stat-row">
                        <div>
                            <span class="l-stat-num"><em><?= MAX_FILE_SIZE_LABEL ?></em></span>
                            <span class="l-stat-lbl"><?= t('landing.perf.stat1.lbl') ?></span>
                        </div>
                        <div>
                            <span class="l-stat-num">~<em>O(1)</em></span>
                            <span class="l-stat-lbl"><?= t('landing.perf.stat2.lbl') ?></span>
                        </div>
                        <div>
                            <span class="l-stat-num"><em>0</em></span>
                            <span class="l-stat-lbl"><?= t('landing.perf.stat3.lbl') ?></span>
                        </div>
                    </div>
                </div>

                <div class="l-perf-card" aria-hidden="true">
                    <div class="l-perf-card-bar">
                        <span><?= t('landing.perf.bench.title') ?></span>
                        <span><?= t('landing.perf.bench.sub') ?></span>
                    </div>

                    <div class="l-perf-row">
                        <span class="l-perf-row-name">doc.pdf <span class="l-pill">2 MB</span></span>
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
                        <span class="l-perf-row-name">corpus.pdf <span class="l-pill"><?= MAX_FILE_SIZE_LABEL ?></span></span>
                        <span class="l-perf-bar"><span class="l-perf-bar-fill" style="--w:100%"></span></span>
                        <span class="l-perf-row-time">29.4 s</span>
                    </div>
                </div>

            </div>
        </div>
    </section>

    <!-- ── Sign-in section ───────────────────────────────── -->
    <?php if ($session !== null): ?>
    <section class="l-section l-signin" id="signin">
        <div class="l-container">
            <span class="l-eyebrow"><?= t('landing.signin.eyebrow') ?></span>
            <h2 class="l-h2" style="margin-inline:auto;text-align:center"><?= t('landing.signin.title') ?></h2>
            <p class="l-lead" style="margin-inline:auto;text-align:center"><?= t('landing.signin.lead') ?></p>

            <div class="l-signin-card">
                <div class="l-signin-icon" aria-hidden="true">
                    <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 11h-6M19 8v6"/></svg>
                </div>
                <h3><?= t('landing.signin.card.title') ?></h3>
                <p><?= t('landing.signin.card.lead') ?></p>

                <a href="<?= $session?->isAuthenticated() ? '/dashboard' : '/login' ?>" class="l-btn l-btn--primary l-google-btn" style="display:flex">
                    <?= $session?->isAuthenticated() ? t('nav.dashboard') : t('landing.signin.btn') ?>
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
                </a>

                <div class="l-signin-or"><?= t('landing.signin.or') ?></div>

                <a href="/app" class="l-btn l-btn--ghost l-google-btn" style="display:flex">
                    <?= t('landing.signin.alt') ?>
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
                </a>
            </div>
        </div>
    </section>
    <?php endif; ?>

</main>

<!-- ── Footer ─────────────────────────────────────────────── -->
<?php
$footerText  = t('footer.text', e(APP_NAME));
$footerLinks = [
    ['href' => '/app', 'label' => t('footer.app')],
    ['href' => '/api', 'label' => t('footer.api')],
    ['href' => 'https://daringfireball.net/projects/markdown/', 'label' => t('footer.about_md'), 'external' => true],
    ['href' => '/terms',   'label' => t('nav.terms')],
    ['href' => '/privacy', 'label' => t('nav.privacy')],
];
require BASE_PATH . '/src/View/footer.php';
?>

</body>
</html>
