<?php

declare(strict_types=1);

namespace Data2Md;

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
