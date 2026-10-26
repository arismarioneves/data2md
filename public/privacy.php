<?php

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));

/** @var \Data2Md\Config\Config $config */
$config = require BASE_PATH . '/src/bootstrap.php';
require BASE_PATH . '/src/I18n/bootstrap.php';
require BASE_PATH . '/src/Auth/bootstrap.php';

use Data2Md\Billing\Entitlement;

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: strict-origin-when-cross-origin');
header("Content-Security-Policy: default-src 'self'; style-src 'self' 'unsafe-inline'; script-src 'self' 'unsafe-inline'; img-src 'self' data:");

$metaTitle = t('legal.privacy.title', APP_NAME);
$metaDesc  = t('legal.privacy.meta');

$legalHeading = t('legal.privacy.heading');
$legalUpdated = t('legal.updated');

$sections = ['controller', 'upload', 'markdown', 'filename', 'account'];

// Naming a processor that handles nothing on this deployment would be a claim
// about data that is never sent.
if (BILLING_ENABLED) {
    $sections[] = 'payment';
}

$sections = [...$sections, 'token', 'ip', 'reach', 'cookies', 'deletion'];

$legalSections = [];

foreach ($sections as $name) {
    $legalSections[] = [
        'h' => t("legal.privacy.$name.h"),
        'b' => t(
            "legal.privacy.$name.b",
            $tiers->for(Entitlement::ANON)->maxFileSizeLabel(),
            $tiers->for(Entitlement::FREE)->maxFileSizeLabel(),
            $tiers->for(Entitlement::PREMIUM)->maxFileSizeLabel(),
            CONTACT_EMAIL,
            e(APP_NAME),
            $config->int('AUDIT_LOG_RETENTION_DAYS')
        ),
    ];
}

require BASE_PATH . '/src/View/legal.php';
