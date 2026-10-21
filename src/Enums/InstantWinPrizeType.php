<?php

declare(strict_types=1);

namespace Digitonic\DrawHouse\Enums;

/**
 * `prizeType` of an `instant_win.revealed` event.
 */
enum InstantWinPrizeType: string
{
    case INSTANT_CASH = 'INSTANT_CASH';
    case INSTANT_SITE_CREDIT = 'INSTANT_SITE_CREDIT';
    case INSTANT_PHYSICAL = 'INSTANT_PHYSICAL';
    case INSTANT_VOUCHER = 'INSTANT_VOUCHER';
}
