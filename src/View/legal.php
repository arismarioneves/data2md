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

require_once __DIR__ . '/helpers.php';
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
                    <h1 class="docs-title"><?= e($legalHeading) ?></h1>
                    <p class="docs-sub"><?= e($legalUpdated) ?></p>
                </header>

                <?php foreach ($legalSections as $section): ?>
                <section class="docs-section">
                    <h2><?= e($section['h']) ?></h2>
                    <p><?= $section['b'] ?></p>
                </section>
                <?php endforeach; ?>
            </article>
        </div>
    </div>
</main>

<?php
$footerText  = t('footer.text', e(APP_NAME));
$footerLinks = [
    ['href' => '/',        'label' => t('footer.home')],
    ['href' => '/app',     'label' => t('footer.converter')],
    ['href' => '/terms',   'label' => t('nav.terms')],
    ['href' => '/privacy', 'label' => t('nav.privacy')],
];
require BASE_PATH . '/src/View/footer.php';
?>

</body>
</html>
