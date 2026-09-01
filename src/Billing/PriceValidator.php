<?php

declare(strict_types=1);

namespace Data2Md\Billing;

/**
 * Checks that the configured Stripe price is one this app can actually charge.
 *
 * A Stripe price is immutable and cannot be deleted, only archived, so a wrong
 * one is a permanent artefact in the account. That makes checking it worth more
 * here than almost anywhere else: the fix is always a new price plus a config
 * change, never an edit.
 */
final class PriceValidator
{
    public const EXPECTED_CURRENCY = 'usd';
    public const REQUIRED_CURRENCY_OPTION = 'brl';
    public const EXPECTED_INTERVAL = 'month';

    /**
     * @param array<string,mixed> $price a Stripe Price with currency_options expanded
     * @return list<string> every problem found, empty when the price is usable
     */
    public static function problems(array $price, int $mirrorUsd, int $mirrorBrl): array
    {
        $problems = [];

        if (($price['object'] ?? '') !== 'price') {
            return ['not a price object'];
        }

        if (($price['active'] ?? false) !== true) {
            $problems[] = 'the price is archived, so a checkout with it fails';
        }

        $recurring = $price['recurring'] ?? null;

        if (!is_array($recurring)) {
            $problems[] = 'the price is one-off, not recurring';
        } else {
            if (($recurring['interval'] ?? '') !== self::EXPECTED_INTERVAL) {
                $problems[] = sprintf(
                    'interval is %s, expected %s',
                    (string) ($recurring['interval'] ?? 'none'),
                    self::EXPECTED_INTERVAL
                );
            }

            if ((int) ($recurring['interval_count'] ?? 1) !== 1) {
                $problems[] = 'interval_count is not 1';
            }
        }

        $currency = strtolower((string) ($price['currency'] ?? ''));

        if ($currency !== self::EXPECTED_CURRENCY) {
            $problems[] = sprintf('default currency is %s, expected %s', $currency ?: 'none', self::EXPECTED_CURRENCY);
        }

        $options = $price['currency_options'] ?? null;

        // Not an optimisation. A Brazilian card can only be charged in BRL, so
        // without this option every domestic checkout fails with
        // currency_not_supported -- and a test card in USD never shows it.
        if (!is_array($options) || !isset($options[self::REQUIRED_CURRENCY_OPTION])) {
            $problems[] = sprintf(
                'no %s currency option: every card issued in Brazil would be declined '
                . '(if the price is correct, the reader forgot expand[]=currency_options)',
                strtoupper(self::REQUIRED_CURRENCY_OPTION)
            );

            $options = [];
        }

        $problems = [
            ...$problems,
            ...self::mirrorProblems('PREMIUM_PRICE_USD', $mirrorUsd, $price['unit_amount'] ?? null, 'usd'),
        ];

        if (isset($options[self::REQUIRED_CURRENCY_OPTION])) {
            $problems = [
                ...$problems,
                ...self::mirrorProblems(
                    'PREMIUM_PRICE_BRL',
                    $mirrorBrl,
                    $options[self::REQUIRED_CURRENCY_OPTION]['unit_amount'] ?? null,
                    'brl'
                ),
            ];
        }

        return $problems;
    }

    /**
     * The env mirrors only render the pricing page; the charge always uses the
     * price. A drift means the page quotes one number and the card is billed
     * another, which is a refund request and a chargeback risk, not a typo.
     *
     * @return list<string>
     */
    private static function mirrorProblems(string $key, int $mirror, mixed $cents, string $currency): array
    {
        if (!is_numeric($cents)) {
            return [sprintf('%s: the price has no %s amount to compare against', $key, strtoupper($currency))];
        }

        $actual = (int) $cents;

        if ($mirror * 100 !== $actual) {
            return [sprintf(
                '%s is %d but the price charges %s %s: the pricing page would quote the wrong amount',
                $key,
                $mirror,
                strtoupper($currency),
                number_format($actual / 100, 2)
            )];
        }

        return [];
    }
}
