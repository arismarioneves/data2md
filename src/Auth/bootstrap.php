<?php

declare(strict_types=1);

/**
 * Exposes to a page:
 *
 *   $session    — the visitor's session, or null when accounts are switched off
 *   $tiers      — the tier catalogue
 *   $viewerTier — what this visitor may do, so a page states their limit rather
 *                 than the deployment ceiling
 *   $apiTier    — what the HTTP API allows, which is what a subscription buys
 *
 * Costs an anonymous visitor nothing: Database connects lazily on its first
 * pdo() call, and Session::load() returns without one when there is no cookie.
 *
 * @var \Data2Md\Config\Config $config
 */

use Data2Md\Auth\Session;
use Data2Md\Auth\SessionStore;
use Data2Md\Auth\TierResolver;
use Data2Md\Billing\Entitlement;
use Data2Md\Storage\Database;

$tiers   = TierResolver::fromConfig($config);
$session = null;

if ($config->bool('ACCOUNTS_ENABLED')) {
    $session = Session::fromConfig($config, new SessionStore(
        new Database($config->string('APP_DATA_DIR') . '/db/mappings.sqlite'),
        $config->int('SESSION_LIFETIME_DAYS'),
        $config->int('SESSION_IDLE_DAYS'),
    ));

    $session->load($_COOKIE);
}

$viewerTier = $tiers->forUser($session?->user());
$apiTier    = $tiers->for(Entitlement::PREMIUM);
