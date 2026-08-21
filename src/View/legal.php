<?php

declare(strict_types=1);

/**
 * Shared layout for the legal pages.
 *
 * Define before requiring:
 *   $legalHeading  — already-translated page heading
 *   $legalUpdated  — already-translated "last updated" line
 *   $legalSections — list of ['h' => heading, 'b' => body html]
 *
 * @var string $legalHeading
 * @var string $legalUpdated
 * @var list<array{h:string,b:string}> $legalSections
 */

use Data2Md\I18n\I18n;

$back = htmlspecialchars(I18n::sanitizeBack($_SERVER['REQUEST_URI'] ?? '/'), ENT_QUOTES);
$esc  = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES);
?>
<!DOCTYPE html>
<html lang="<?= I18n::current() ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php require BASE_PATH . '/src/View/head-meta.php'; ?>
    <link rel="stylesheet" href="/assets/css/landing.css">
    <link rel="stylesheet" href="/assets/css/docs.css">
</head>
<body>

<header class="l-header" id="l-header">
    <div class="l-container">
        <div class="l-header-inner">
            <a href="/" class="l-logo" aria-label="<?= $esc(APP_NAME) ?> home">
                <img class="l-logo-mark" src="/assets/img/icon.svg" width="30" height="30" alt="" aria-hidden="true">
                <span class="l-logo-name"><?= $esc(APP_NAME) ?></span>
            </a>
            <nav class="l-nav">
                <a href="/" class="l-nav-link" data-hide-sm><?= t('nav.about') ?></a>
                <a href="/api" class="l-nav-link"><?= t('nav.api') ?></a>
                <a href="/app" class="l-nav-cta">
                    <?= t('nav.app') ?>
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
                </a>
                <span class="l-lang" role="group" aria-label="<?= $esc(t('lang.aria')) ?>">
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
                    <h1 class="docs-title"><?= $esc($legalHeading) ?></h1>
                    <p class="docs-sub"><?= $esc($legalUpdated) ?></p>
                </header>

                <?php foreach ($legalSections as $section): ?>
                <section class="docs-section">
                    <h2><?= $esc($section['h']) ?></h2>
                    <p><?= $section['b'] ?></p>
                </section>
                <?php endforeach; ?>
            </article>
        </div>
    </div>
</main>

<footer class="l-footer">
    <div class="l-container">
        <div class="l-footer-inner">
            <p class="l-footer-text"><?= t('footer.text', $esc(APP_NAME)) ?></p>
            <nav class="l-footer-links">
                <a href="/"><?= t('footer.home') ?></a>
                <a href="/app"><?= t('footer.converter') ?></a>
                <a href="/terms"><?= t('nav.terms') ?></a>
                <a href="/privacy"><?= t('nav.privacy') ?></a>
            </nav>
        </div>
    </div>
</footer>

</body>
</html>
