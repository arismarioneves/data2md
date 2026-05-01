<?php

declare(strict_types=1);

use Data2Md\I18n\I18n;

if (!function_exists('t')) {
    /**
     * Translate a key, optionally interpolating sprintf args.
     */
    function t(string $key, mixed ...$args): string
    {
        return I18n::t($key, ...$args);
    }
}
