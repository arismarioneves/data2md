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

$metaTitle = t('legal.terms.title', APP_NAME);
$metaDesc  = t('legal.terms.meta');

$legalHeading = t('legal.terms.heading');
$legalUpdated = t('legal.updated');

// Named rather than numbered, so a section that stops being true is findable by
// what it claims. The payment sections are omitted when there is nothing to buy.
$sections = ['service', 'accounts', 'conduct', 'limits'];

if (BILLING_ENABLED) {
    $sections[] = 'subscription';
    $sections[] = 'cancel';
}

$sections = [...$sections, 'availability', 'api', 'removal', 'changes'];

$legalSections = [];

foreach ($sections as $name) {
    $legalSections[] = [
        'h' => t("legal.terms.$name.h"),
        'b' => t(
            "legal.terms.$name.b",
            $tiers->for(Entitlement::ANON)->maxFileSizeLabel(),
            $tiers->for(Entitlement::FREE)->maxFileSizeLabel(),
            $tiers->for(Entitlement::PREMIUM)->maxFileSizeLabel(),
            CONTACT_EMAIL
        ),
    ];
}

require BASE_PATH . '/src/View/legal.php';
