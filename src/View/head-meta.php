<?php

declare(strict_types=1);

/**
 * Shared <head> metadata: title, description, icons and social preview tags.
 *
 * Define before requiring:
 *   $metaTitle — full, already-translated page title
 *   $metaDesc  — already-translated page description
 *
 * og:title always mirrors $metaTitle; it has no separate translation key.
 */

use Data2Md\I18n\I18n;

/** @var string $metaTitle */
/** @var string $metaDesc */
$metaTitle = $metaTitle ?? APP_NAME;
$metaDesc  = $metaDesc  ?? '';

if (defined('APP_URL') && APP_URL !== '') {
    $origin = rtrim(APP_URL, '/');
} else {
    // HTTP_HOST is client-controlled, so keep it to host characters only.
    $secure = ($_SERVER['HTTPS'] ?? '') === 'on'
           || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
    $host   = preg_replace('/[^A-Za-z0-9.\-:]/', '', $_SERVER['HTTP_HOST'] ?? '');
    $origin = ($secure ? 'https' : 'http') . '://' . substr($host ?: 'localhost', 0, 255);
}

$path     = strtok($_SERVER['REQUEST_URI'] ?? '/', '?');
$ogUrl    = $origin . I18n::sanitizeBack(is_string($path) ? $path : '/');
$ogImage  = $origin . '/assets/img/og-image.png';
$ogLocale = I18n::current() === 'pt' ? 'pt_BR' : 'en_US';

$esc = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES);
?>
    <title><?= $esc($metaTitle) ?></title>
    <meta name="description" content="<?= $esc($metaDesc) ?>">

    <?php /* No SVG favicon: the M+arrow mark is unreadable at 16px. The .ico
             holds separate art per size — 16 is the M alone, 32 and 48 add
             the arrow. */ ?>
    <link rel="icon" href="/favicon.ico" sizes="16x16 32x32 48x48">
    <link rel="apple-touch-icon" href="/assets/img/apple-touch-icon.png">
    <link rel="manifest" href="/site.webmanifest">
    <meta name="theme-color" content="#f8f5ee">

    <meta property="og:type" content="website">
    <meta property="og:site_name" content="<?= $esc(APP_NAME) ?>">
    <meta property="og:title" content="<?= $esc($metaTitle) ?>">
    <meta property="og:description" content="<?= $esc($metaDesc) ?>">
    <meta property="og:url" content="<?= $esc($ogUrl) ?>">
    <meta property="og:locale" content="<?= $esc($ogLocale) ?>">
    <meta property="og:image" content="<?= $esc($ogImage) ?>">
    <meta property="og:image:type" content="image/png">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta property="og:image:alt" content="<?= $esc(t('og.image.alt')) ?>">

    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?= $esc($metaTitle) ?>">
    <meta name="twitter:description" content="<?= $esc($metaDesc) ?>">
    <meta name="twitter:image" content="<?= $esc($ogImage) ?>">
