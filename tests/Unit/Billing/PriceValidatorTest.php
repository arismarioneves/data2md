<?php

declare(strict_types=1);

namespace Data2Md\Tests\Unit\Billing;

use Data2Md\Billing\PriceValidator;
use PHPUnit\Framework\TestCase;

final class PriceValidatorTest extends TestCase
{
    /**
     * The shape the API actually returns for the price this app charges, taken
     * from a real test-mode retrieve with currency_options expanded.
     *
     * @param array<string,mixed> $overrides
     * @return array<string,mixed>
     */
    private static function price(array $overrides = []): array
    {
        return [
            'object'      => 'price',
            'id'          => 'price_123',
            'active'      => true,
            'currency'    => 'usd',
            'unit_amount' => 1900,
            'recurring'   => [
                'interval'       => 'month',
                'interval_count' => 1,
                'usage_type'     => 'licensed',
            ],
            'currency_options' => [
                'usd' => ['unit_amount' => 1900, 'tax_behavior' => 'unspecified'],
                'brl' => ['unit_amount' => 9500, 'tax_behavior' => 'unspecified'],
            ],
            ...$overrides,
        ];
    }

    public function testAWellFormedPriceHasNoProblems(): void
    {
        self::assertSame([], PriceValidator::problems(self::price(), 19, 95));
    }

    /**
     * The failure this check exists for: a test card in USD passes, and the
     * first real Brazilian customer is declined with currency_not_supported.
     */
    public function testMissingBrlOptionIsReported(): void
    {
        $problems = PriceValidator::problems(
            self::price(['currency_options' => ['usd' => ['unit_amount' => 1900]]]),
            19,
            95
        );

        self::assertNotSame([], $problems);
        self::assertStringContainsString('BRL', $problems[0]);
        self::assertStringContainsString('Brazil', $problems[0]);
    }

    /** currency_options is absent unless expanded, so say so in the message. */
    public function testAbsentCurrencyOptionsMentionsTheExpand(): void
    {
        $problems = PriceValidator::problems(self::price(['currency_options' => null]), 19, 95);

        self::assertStringContainsString('expand[]=currency_options', implode("\n", $problems));
    }

    public function testArchivedPriceIsReported(): void
    {
        $problems = PriceValidator::problems(self::price(['active' => false]), 19, 95);

        self::assertStringContainsString('archived', implode("\n", $problems));
    }

    public function testOneOffPriceIsReported(): void
    {
        $problems = PriceValidator::problems(self::price(['recurring' => null]), 19, 95);

        self::assertStringContainsString('not recurring', implode("\n", $problems));
    }

    public function testYearlyIntervalIsReported(): void
    {
        $problems = PriceValidator::problems(
            self::price(['recurring' => ['interval' => 'year', 'interval_count' => 1]]),
            19,
            95
        );

        self::assertStringContainsString('interval is year', implode("\n", $problems));
    }

    public function testQuarterlyIntervalIsReported(): void
    {
        $problems = PriceValidator::problems(
            self::price(['recurring' => ['interval' => 'month', 'interval_count' => 3]]),
            19,
            95
        );

        self::assertStringContainsString('interval_count', implode("\n", $problems));
    }

    public function testNonUsdDefaultCurrencyIsReported(): void
    {
        $problems = PriceValidator::problems(
            self::price(['currency' => 'brl', 'unit_amount' => 9500]),
            19,
            95
        );

        self::assertStringContainsString('default currency is brl', implode("\n", $problems));
    }

    /** A page quoting one number while the card is billed another is a chargeback. */
    public function testDriftedUsdMirrorIsReported(): void
    {
        $problems = PriceValidator::problems(self::price(), 29, 95);

        self::assertStringContainsString('PREMIUM_PRICE_USD is 29', implode("\n", $problems));
        self::assertStringContainsString('19.00', implode("\n", $problems));
    }

    public function testDriftedBrlMirrorIsReported(): void
    {
        $problems = PriceValidator::problems(self::price(), 19, 99);

        self::assertStringContainsString('PREMIUM_PRICE_BRL is 99', implode("\n", $problems));
        self::assertStringContainsString('95.00', implode("\n", $problems));
    }

    public function testEveryProblemIsReportedAtOnce(): void
    {
        $problems = PriceValidator::problems(
            self::price([
                'active'           => false,
                'currency'         => 'eur',
                'recurring'        => ['interval' => 'year', 'interval_count' => 2],
                'currency_options' => null,
            ]),
            29,
            99
        );

        self::assertGreaterThanOrEqual(5, count($problems), implode("\n", $problems));
    }

    public function testSomethingThatIsNotAPriceIsRejectedOutright(): void
    {
        self::assertSame(['not a price object'], PriceValidator::problems(['object' => 'product'], 19, 95));
        self::assertSame(['not a price object'], PriceValidator::problems([], 19, 95));
    }
}
