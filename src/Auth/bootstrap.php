<?php

declare(strict_types=1);

/**
 * Exposes $session to a page, or null when accounts are switched off.
 *
 * Costs an anonymous visitor nothing: Database connects lazily on its first
 * pdo() call, and Session::load() returns without one when there is no cookie.
 *
 * @var \Data2Md\Config\Config $config
 */

use Data2Md\Auth\Session;
use Data2Md\Auth\SessionStore;
use Data2Md\Storage\Database;

$session = null;

if ($config->bool('ACCOUNTS_ENABLED')) {
    $session = Session::fromConfig($config, new SessionStore(
        new Database($config->string('APP_DATA_DIR') . '/db/mappings.sqlite'),
        $config->int('SESSION_LIFETIME_DAYS'),
        $config->int('SESSION_IDLE_DAYS'),
    ));

    $session->load($_COOKIE);
}
