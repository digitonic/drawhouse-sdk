<?php

declare(strict_types=1);

namespace Digitonic\DrawHouse\Support;

/**
 * What a winner may take in cash instead of a physical prize.
 *
 * DrawHouse states no cash figure per prize. It gives each game a
 * `physicalToCashPercentage` (73 is 73%), omitted when cash-for-physical is
 * not offered, and the figure is that share of the prize's value, rounded
 * down to whole pounds as the brands word it ("£230 AirPods or £167 cash").
 *
 * This is the same rule as the PrizeJoe storefront's catalogue
 * (`App\Catalogue\Prize::fromPlatform()`), so every Digitonic surface shows
 * the same figure: only prizes whose category starts with PHYSICAL (which
 * includes DrawHouse's PHYSICAL_NO_VAT), only a percentage above 0 and at
 * most 100, and only a positive value. Vouchers, cash and site credit have
 * none.
 */
final class CashAlternative
{
    /**
     * @param  float|null  $amount  The prize's value.
     * @param  string|null  $category  The prize's category as DrawHouse sent it (raw, so PHYSICAL_NO_VAT counts).
     * @param  float|null  $physicalToCashPercentage  The game's percentage, null when not offered.
     * @return float|null Whole pounds, or null when there is no cash alternative.
     */
    public static function for(?float $amount, ?string $category, ?float $physicalToCashPercentage): ?float
    {
        if (! str_starts_with(strtoupper(trim((string) $category)), 'PHYSICAL')) {
            return null;
        }

        if ($physicalToCashPercentage === null || $physicalToCashPercentage <= 0 || $physicalToCashPercentage > 100) {
            return null;
        }

        if ($amount === null || $amount <= 0) {
            return null;
        }

        $cash = floor($amount * $physicalToCashPercentage / 100);

        return $cash > 0 ? $cash : null;
    }
}
