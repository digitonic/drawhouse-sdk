<?php

declare(strict_types=1);

namespace Digitonic\DrawHouse\Enums;

/**
 * How a draw prize reaches the winner, on `draw.won`.
 */
enum PrizeFulfilment: string
{
    /** Cash paid straight to the player's wallet. */
    case PAID_TO_WALLET = 'PAID_TO_WALLET';

    /** Awarded as site credit. */
    case SITE_CREDIT = 'SITE_CREDIT';

    /** A physical or voucher prize the winner has to claim. */
    case AWAITING_CLAIM = 'AWAITING_CLAIM';
}
