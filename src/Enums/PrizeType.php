<?php

declare(strict_types=1);

namespace Digitonic\DrawHouse\Enums;

/**
 * `Prize.type` on charity-raffle games and live draws.
 *
 * Partner API v1.2 documents exactly two values: DRAW and INSTANT.
 * v1.0 listed INSTANT_CASH, INSTANT_SITE_CREDIT and INSTANT_PHYSICAL; those
 * cases are kept so existing consumers keep compiling and so any value the
 * API still emits parses, but they are deprecated. The prize's kind is now
 * carried by `Prize.category` (CASH, SITE_CREDIT, PHYSICAL, VOUCHER).
 */
enum PrizeType: string
{
    case DRAW = 'DRAW';
    case INSTANT = 'INSTANT';

    /** @deprecated v1.2 uses INSTANT with category CASH. */
    case INSTANT_CASH = 'INSTANT_CASH';

    /** @deprecated v1.2 uses INSTANT with category SITE_CREDIT. */
    case INSTANT_SITE_CREDIT = 'INSTANT_SITE_CREDIT';

    /** @deprecated v1.2 uses INSTANT with category PHYSICAL. */
    case INSTANT_PHYSICAL = 'INSTANT_PHYSICAL';

    public function isInstant(): bool
    {
        return $this !== self::DRAW;
    }

    public function isDraw(): bool
    {
        return $this === self::DRAW;
    }

    /**
     * True for the pre-v1.2 INSTANT_* spellings.
     */
    public function isLegacy(): bool
    {
        return $this !== self::DRAW && $this !== self::INSTANT;
    }
}
