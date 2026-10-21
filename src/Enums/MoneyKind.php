<?php

declare(strict_types=1);

namespace Digitonic\DrawHouse\Enums;

/**
 * `kind` of a row on the money feed (GET /v2/partners/transactions).
 */
enum MoneyKind: string
{
    /** Card deposit. */
    case DEPOSIT = 'DEPOSIT';

    /** Cash credited by DrawHouse staff. */
    case ADMIN_CREDIT = 'ADMIN_CREDIT';

    /** A checkout: one basket, possibly many tickets. */
    case PURCHASE = 'PURCHASE';

    /** Cash returned after a round cancellation or a player exclusion. */
    case REFUND = 'REFUND';

    /** A cash prize paid to the wallet. */
    case PRIZE_PAYOUT = 'PRIZE_PAYOUT';

    /** Cash paid out to the player. */
    case WITHDRAWAL = 'WITHDRAWAL';

    case SITE_CREDIT_AWARD = 'SITE_CREDIT_AWARD';
    case SITE_CREDIT_SPEND = 'SITE_CREDIT_SPEND';
    case SITE_CREDIT_EXPIRY = 'SITE_CREDIT_EXPIRY';
    case SITE_CREDIT_ADJUSTMENT = 'SITE_CREDIT_ADJUSTMENT';

    /**
     * Whether the row moves the player's site-credit balance rather than cash.
     */
    public function isSiteCredit(): bool
    {
        return str_starts_with($this->value, 'SITE_CREDIT_');
    }
}
