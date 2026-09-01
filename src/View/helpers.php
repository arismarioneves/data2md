<?php

declare(strict_types=1);

if (!function_exists('e')) {
    /** Escape for HTML text and quoted attributes. */
    function e(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('time_tag')) {
    /**
     * A timestamp the reader's own browser can restate in their own zone.
     *
     * The server does not know where the reader is, so it renders UTC and says
     * so, and a script on the page rewrites it to local time. Without that
     * script the label is still true, which is the part that matters: an
     * unlabelled UTC time does not look wrong, it looks like a local time that
     * is three hours out.
     */
    function time_tag(int $timestamp, string $fallbackFormat): string
    {
        return sprintf(
            '<time datetime="%s" data-local>%s UTC</time>',
            e(gmdate(DATE_ATOM, $timestamp)),
            e(gmdate($fallbackFormat, $timestamp))
        );
    }
}
