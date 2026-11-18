<?php

declare(strict_types=1);

namespace Digitonic\DrawHouse\Support;

use Digitonic\DrawHouse\DTO\LiveDraws\LivePrize;
use Digitonic\DrawHouse\DTO\Prize;
use Digitonic\DrawHouse\Enums\PrizeType;

/**
 * What a draw's winner may take in cash instead of its prize, exactly as
 * DrawHouse's own player front end works it out (`resolveDrawCashAlternative`
 * on the brands' prize-draw pages). DrawHouse's API states
 * no cash figure; it gives each game a `physicalToCashPercentage`.
 *
 * - Only the draw's main prize: the first prize of type DRAW. An instant win
 *   has none, and neither does a draw with instant wins only.
 * - Only these categories: PHYSICAL, PHYSICAL_NO_VAT, VOUCHER, VOUCHER_VAT.
 *   Cash and site credit have none.
 * - The category is the brand edition's own where it has overridden the base
 *   game's (a prize the base game lists as CASH, overridden as PHYSICAL). The live
 *   feed's prizes carry the edition's; the games catalogue's do not.
 * - The percentage of the prize's value, to the penny, then rounded down to
 *   whole pounds. Any percentage above 0 counts; none or 0 gives nothing.
 */
final class CashAlternative
{
    /** The prize categories DrawHouse offers a cash alternative on. */
    public const CATEGORIES = ['PHYSICAL', 'PHYSICAL_NO_VAT', 'VOUCHER', 'VOUCHER_VAT'];

    /**
     * The cash alternative for one main draw prize.
     *
     * @param  float|null  $amount  The prize's value.
     * @param  string|null  $category  Its category as DrawHouse sent it (raw, so PHYSICAL_NO_VAT and VOUCHER_VAT count).
     * @param  float|null  $physicalToCashPercentage  The game's percentage.
     * @return float|null Whole pounds, or null when there is no cash alternative.
     */
    public static function for(?float $amount, ?string $category, ?float $physicalToCashPercentage): ?float
    {
        if (! in_array(strtoupper(trim((string) $category)), self::CATEGORIES, true)) {
            return null;
        }

        if ($physicalToCashPercentage === null || $physicalToCashPercentage <= 0 || $amount === null || $amount <= 0) {
            return null;
        }

        // DrawHouse: Math.floor(Math.round(amount * pct / 100 * 100) / 100).
        $cash = floor(round($amount * $physicalToCashPercentage) / 100);

        return $cash > 0 ? $cash : null;
    }

    /**
     * The cash alternative of a draw: its first DRAW prize's, as DrawHouse
     * picks it. Pass the live feed's prizes, which carry the edition's
     * categories.
     *
     * @param  iterable<Prize|LivePrize>  $prizes  In the order DrawHouse sent them.
     */
    public static function forDraw(iterable $prizes, ?float $physicalToCashPercentage): ?float
    {
        foreach ($prizes as $prize) {
            if ($prize->type === PrizeType::DRAW && ($prize->raw['isActive'] ?? true) !== false) {
                return $prize->cashAlternative($physicalToCashPercentage);
            }
        }

        return null;
    }
}
