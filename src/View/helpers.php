<?php

declare(strict_types=1);

if (!function_exists('e')) {
    /** Escape for HTML text and quoted attributes. */
    function e(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}
