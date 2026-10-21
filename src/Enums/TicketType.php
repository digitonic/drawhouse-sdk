<?php

declare(strict_types=1);

namespace Digitonic\DrawHouse\Enums;

/**
 * `ticketType` of a ticket. REGULAR is paid for by the player; the others
 * are granted or promotional, and cost 0.
 */
enum TicketType: string
{
    case REGULAR = 'REGULAR';
    case BONUS = 'BONUS';
    case REBUY = 'REBUY';
    case EARLY = 'EARLY';
    case REFERRAL = 'REFERRAL';

    public function isPaid(): bool
    {
        return $this === self::REGULAR;
    }
}
