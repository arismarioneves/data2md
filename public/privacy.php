<?php

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));

/** @var \Data2Md\Config\Config $config */
$config = require BASE_PATH . '/src/bootstrap.php';
require BASE_PATH . '/src/I18n/bootstrap.php';
require BASE_PATH . '/src/Auth/bootstrap.php';

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: strict-origin-when-cross-origin');
header("Content-Security-Policy: default-src 'self'; style-src 'self' 'unsafe-inline'; script-src 'self' 'unsafe-inline'; img-src 'self' data:");

$metaTitle = t('legal.privacy.title', APP_NAME);
$metaDesc  = t('legal.privacy.meta');

$legalHeading = t('legal.privacy.heading');
$legalUpdated = t('legal.updated');

$legalSections = [];
for ($i = 1; $i <= 8; $i++) {
    $legalSections[] = [
        'h' => t("legal.privacy.s$i.h"),
        'b' => t("legal.privacy.s$i.b", MAX_FILE_SIZE_LABEL, RATE_LIMIT_MAX, CONTACT_EMAIL),
    ];
}

require BASE_PATH . '/src/View/legal.php';
