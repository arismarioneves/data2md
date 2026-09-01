<?php

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));

/** @var \Data2Md\Config\Config $config */
$config = require BASE_PATH . '/src/bootstrap.php';
require BASE_PATH . '/src/I18n/bootstrap.php';
require BASE_PATH . '/src/Auth/bootstrap.php';

use Data2Md\Billing\Entitlement;
use Data2Md\I18n\I18n;

if (!$config->bool('BILLING_ENABLED') || $session === null) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Not found.\n";
    exit;
}

$isPremium = Entitlement::isPremium($session->user());
$notice    = match ((string) ($_GET['checkout'] ?? '')) {
    'cancelled' => t('price.flash.cancelled'),
    'error'     => t('price.flash.error'),
    default     => null,
};

header('Cache-Control: private, no-store');
header('Vary: Cookie');
header('Content-Type: text/html; charset=utf-8');
header("Content-Security-Policy: default-src 'self'; style-src 'self' 'unsafe-inline'; script-src 'self' 'unsafe-inline'; img-src 'self' data:");

$metaTitle = t('price.title', APP_NAME);
$metaDesc  = t('price.meta.desc');
?>
<!DOCTYPE html>
<html lang="<?= I18n::current() ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php require BASE_PATH . '/src/View/head-meta.php'; ?>
    <link rel="stylesheet" href="/assets/css/landing.css">
    <link rel="stylesheet" href="/assets/css/pricing.css">
</head>
<body>

<?php
$navLinks = [
    ['href' => '/',        'label' => t('nav.about'), 'hideSm' => true],
    ['href' => '/api',     'label' => t('nav.api')],
];
$navCta = ['href' => '/app', 'label' => t('nav.app'), 'icon' => 'arrow'];
require BASE_PATH . '/src/View/header.php';
?>

<main class="l-main price-main">
    <div class="l-container">

        <header class="price-hero">
            <span class="l-eyebrow"><?= t('price.eyebrow') ?></span>
            <h1 class="price-h1"><?= t('price.heading') ?></h1>
            <p class="price-lead"><?= t('price.lead') ?></p>
        </header>

        <?php if ($notice !== null): ?>
        <p class="price-flash" role="status"><?= e($notice) ?></p>
        <?php endif; ?>

        <div class="price-grid">

            <section class="price-card">
                <h2 class="price-card-name"><?= t('price.free.name') ?></h2>
                <p class="price-amount"><?= t('price.free.amount') ?></p>
                <p class="price-card-sub"><?= t('price.free.sub') ?></p>
                <ul class="price-list">
                    <li><?= t('price.free.f1', $tiers->for(Entitlement::ANON)->maxFileSizeLabel()) ?></li>
                    <li><?= t('price.free.f2', $tiers->for(Entitlement::FREE)->maxFileSizeLabel()) ?></li>
                    <li><?= t('price.free.f3') ?></li>
                    <li class="price-list-off"><?= t('price.free.f4') ?></li>
                </ul>
            </section>

            <section class="price-card price-card--featured">
                <span class="price-badge"><?= t('price.premium.badge') ?></span>
                <h2 class="price-card-name"><?= t('price.premium.name') ?></h2>
                <p class="price-amount">
                    US$ <?= (int) $config->int('PREMIUM_PRICE_USD') ?><span class="price-per"><?= t('price.per.month') ?></span>
                </p>
                <p class="price-alt">R$ <?= (int) $config->int('PREMIUM_PRICE_BRL') ?><?= t('price.per.month') ?></p>
                <p class="price-card-sub"><?= t('price.premium.currency') ?></p>
                <ul class="price-list">
                    <li><?= t('price.premium.f1', $tiers->for(Entitlement::PREMIUM)->maxFileSizeLabel()) ?></li>
                    <li><?= t(
                        'price.premium.f2',
                        $tiers->for(Entitlement::PREMIUM)->maxConversions,
                        $tiers->for(Entitlement::PREMIUM)->windowSeconds
                    ) ?></li>
                    <li><?= t('price.premium.f3') ?></li>
                    <li><?= t('price.premium.f4') ?></li>
                </ul>

                <?php if (!$session->isAuthenticated()): ?>
                    <a class="l-btn l-btn--primary price-cta" href="/login"><?= t('price.cta.signin') ?></a>
                    <p class="price-note"><?= t('price.cta.signin.note') ?></p>
                <?php elseif ($isPremium): ?>
                    <form method="post" action="/billing/portal">
                        <input type="hidden" name="csrf" value="<?= e((string) $session->csrfToken()) ?>">
                        <button type="submit" class="l-btn l-btn--ghost price-cta"><?= t('price.cta.manage') ?></button>
                    </form>
                    <p class="price-note"><?= t('price.cta.current') ?></p>
                <?php else: ?>
                    <form method="post" action="/billing/checkout">
                        <input type="hidden" name="csrf" value="<?= e((string) $session->csrfToken()) ?>">
                        <button type="submit" class="l-btn l-btn--primary price-cta"><?= t('price.cta.subscribe') ?></button>
                    </form>
                    <p class="price-note"><?= t('price.cta.note') ?></p>
                <?php endif; ?>
            </section>

        </div>

        <section class="price-faq">
            <h2 class="price-faq-head"><?= t('price.faq.head') ?></h2>
            <dl>
                <dt><?= t('price.faq.q1') ?></dt>
                <dd><?= t('price.faq.a1') ?></dd>
                <dt><?= t('price.faq.q2') ?></dt>
                <dd><?= t('price.faq.a2') ?></dd>
                <dt><?= t('price.faq.q3') ?></dt>
                <dd><?= t('price.faq.a3') ?></dd>
                <dt><?= t('price.faq.q4') ?></dt>
                <dd><?= t('price.faq.a4') ?></dd>
            </dl>
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

</body>
</html>
