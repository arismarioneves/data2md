<?php

declare(strict_types=1);

/**
 * Page chrome: logo, nav, language switch.
 *
 * Define before requiring:
 *   $navLinks — list of ['href' => string, 'label' => string, 'hideSm' => bool]
 *   $navCta   — ['href' => string, 'label' => string, 'icon' => 'arrow'|'signin'],
 *               optional; omit on a page that is its own destination
 *
 * $session, when a page has required src/Auth/bootstrap.php, adds the sign-in
 * or dashboard entry so every page agrees about it. The plans link appears the
 * same way, from BILLING_ENABLED.
 *
 * @var list<array{href:string,label:string,hideSm?:bool}> $navLinks
 * @var array{href:string,label:string,icon:string}|null $navCta
 * @var \Data2Md\Auth\Session|null $session
 */

use Data2Md\I18n\I18n;

require_once __DIR__ . '/helpers.php';

$navCta  = $navCta ?? null;
$session = $session ?? null;

if (defined('BILLING_ENABLED') && BILLING_ENABLED) {
    $navLinks[] = ['href' => '/pricing', 'label' => t('nav.pricing')];
}

if ($session !== null) {
    $navLinks[] = $session->isAuthenticated()
        ? ['href' => '/dashboard', 'label' => t('nav.dashboard')]
        : ['href' => '/login', 'label' => t('nav.signin')];
}

$back = e(I18n::sanitizeBack($_SERVER['REQUEST_URI'] ?? '/'));

$ctaIcons = [
    'arrow'  => '<path d="M5 12h14M13 6l6 6-6 6"/>',
    'signin' => '<path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4M10 17l5-5-5-5M15 12H3"/>',
];
?>
<header class="l-header" id="l-header">
    <div class="l-container">
        <div class="l-header-inner">
            <a href="/" class="l-logo" aria-label="<?= e(APP_NAME) ?> home">
                <img class="l-logo-mark" src="/assets/img/icon.svg" width="30" height="30" alt="" aria-hidden="true">
                <span class="l-logo-name"><?= e(APP_NAME) ?></span>
            </a>
            <nav class="l-nav">
                <?php foreach ($navLinks as $link): ?>
                <a href="<?= e($link['href']) ?>" class="l-nav-link"<?= ($link['hideSm'] ?? false) ? ' data-hide-sm' : '' ?>><?= e($link['label']) ?></a>
                <?php endforeach; ?>
                <?php if ($navCta !== null): ?>
                <a href="<?= e($navCta['href']) ?>" class="l-nav-cta">
                    <?= e($navCta['label']) ?>
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><?= $ctaIcons[$navCta['icon']] ?? $ctaIcons['arrow'] ?></svg>
                </a>
                <?php endif; ?>
                <span class="l-lang" role="group" aria-label="<?= e(t('lang.aria')) ?>">
                    <a href="/lang?to=en&amp;back=<?= $back ?>" class="<?= I18n::current() === 'en' ? 'is-active' : '' ?>"><?= t('lang.label.en') ?></a>
                    <span class="l-lang-sep" aria-hidden="true">·</span>
                    <a href="/lang?to=pt&amp;back=<?= $back ?>" class="<?= I18n::current() === 'pt' ? 'is-active' : '' ?>"><?= t('lang.label.pt') ?></a>
                </span>
            </nav>
        </div>
    </div>
</header>
