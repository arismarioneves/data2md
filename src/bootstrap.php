<?php

declare(strict_types=1);

use Data2Md\Config\Config;
use Data2Md\Config\ConfigError;

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/helpers.php';

try {
    $config = Config::fromEnvironment();
} catch (ConfigError $e) {
    error_log('[data2md] ' . $e->getMessage());

    if (PHP_SAPI !== 'cli') {
        http_response_code(500);
        header('Content-Type: text/plain; charset=utf-8');
        echo "Server misconfigured.\n";
    } else {
        fwrite(STDERR, $e->getMessage() . "\n");
    }
    exit(1);
}

// Runtime limits. upload_max_filesize and post_max_size are PHP_INI_PERDIR and
// cannot be set here — the entrypoint writes them into a conf.d file from the
// same environment variables.
ini_set('memory_limit', $config->int('PHP_MEMORY_LIMIT_MB') . 'M');
ini_set(
    'max_execution_time',
    (string) ($config->int('CONVERT_TIMEOUT_SECONDS') + $config->int('CONVERT_QUEUE_WAIT_SECONDS') + 60)
);
ini_set('display_errors', $config->string('APP_ENV') === 'development' ? '1' : '0');
ini_set('log_errors', '1');

define('APP_ENV',     $config->string('APP_ENV'));
define('APP_NAME',    $config->string('APP_NAME'));
define('APP_URL',     $config->string('APP_URL'));
define('APP_VERSION', $config->string('APP_VERSION'));

define('MAX_FILE_SIZE',       $config->int('MAX_FILE_SIZE_MB') * 1024 * 1024);
define('MAX_FILE_SIZE_LABEL', \Data2Md\format_bytes_label($config->int('MAX_FILE_SIZE_MB')));

define('RATE_LIMIT_MAX',    $config->int('RATE_LIMIT_MAX'));
define('RATE_LIMIT_WINDOW', $config->int('RATE_LIMIT_WINDOW_SECONDS'));

define('CONTACT_EMAIL', $config->string('CONTACT_EMAIL'));

return $config;
