<?php

declare(strict_types=1);

namespace Data2Md;

use Data2Md\Config\Config;

if (!function_exists(__NAMESPACE__ . '\format_bytes_label')) {
    /** Human-readable label for a size given in whole megabytes. */
    function format_bytes_label(int $megabytes): string
    {
        if ($megabytes >= 1024 && $megabytes % 1024 === 0) {
            return intdiv($megabytes, 1024) . ' GB';
        }
        return $megabytes . ' MB';
    }
}

if (!function_exists(__NAMESPACE__ . '\request_ceiling_seconds')) {
    /**
     * Upper bound in seconds for one request: the conversion timeout, plus
     * the queue wait, plus a 60s margin for upload I/O and response
     * overhead. Used for PHP's max_execution_time (src/bootstrap.php) and
     * the client-side upload timeout (public/app.php).
     *
     * docker/entrypoint.sh computes the same formula in shell — before PHP
     * ever runs, to size Apache's Timeout and post_max_size — and cannot
     * call into this function. If this formula changes, entrypoint.sh's
     * REQUEST_CEILING must change with it.
     */
    function request_ceiling_seconds(Config $config): int
    {
        return $config->int('CONVERT_TIMEOUT_SECONDS') + $config->int('CONVERT_QUEUE_WAIT_SECONDS') + 60;
    }
}
