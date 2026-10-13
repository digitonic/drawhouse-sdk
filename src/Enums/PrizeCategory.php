<?php

declare(strict_types=1);

namespace Digitonic\DrawHouse\Enums;

/**
 * `Prize.category`. VOUCHER was added in Partner API v1.2.
 */
enum PrizeCategory: string
{
    case PHYSICAL = 'PHYSICAL';
    case CASH = 'CASH';
    case SITE_CREDIT = 'SITE_CREDIT';
    case VOUCHER = 'VOUCHER';
}
