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

if (!function_exists(__NAMESPACE__ . '\format_bytes')) {
    /** Human-readable label for a byte count. */
    function format_bytes(int $bytes): string
    {
        if ($bytes >= 1_048_576) {
            return round($bytes / 1_048_576, 1) . ' MB';
        }

        if ($bytes >= 1024) {
            return round($bytes / 1024, 1) . ' KB';
        }

        return $bytes . ' B';
    }
}

if (!function_exists(__NAMESPACE__ . '\rate_limit_max_window_seconds')) {
    /**
     * Longest window of any rate-limit bucket in play.
     *
     * Every bucket shares one directory under a hashed filename, so collection
     * cannot tell them apart and has to keep files for the longest window. A
     * shorter value silently resets a limiter that is still counting: pass the
     * download bucket's 60 seconds while the anonymous tier counts an hour, and
     * "one conversion per hour" becomes "one per two minutes".
     *
     * Every window belongs here. Adding a bucket without adding its window is
     * the way this breaks.
     */
    function rate_limit_max_window_seconds(Config $config): int
    {
        return max(
            $config->int('RATE_LIMIT_WINDOW_SECONDS'),
            $config->int('TIER_ANON_WINDOW_SECONDS'),
            $config->int('TIER_FREE_WINDOW_SECONDS'),
            $config->int('TIER_FREE_IP_WINDOW_SECONDS'),
            $config->int('TIER_PREMIUM_WINDOW_SECONDS'),
            $config->int('LOGIN_WINDOW_SECONDS'),
        );
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
