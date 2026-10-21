<?php

declare(strict_types=1);

namespace Digitonic\DrawHouse\Enums;

/**
 * `status` of a ticket on the tickets feed (GET /v2/partners/tickets).
 */
enum TicketStatus: string
{
    /** Payment not yet complete. */
    case UNPAID = 'UNPAID';

    /** Legacy fantasy ticket without picks. */
    case NOSELECTION = 'NOSELECTION';

    /** Paid and in the draw. */
    case INPLAY = 'INPLAY';

    /** The draw result: lost. */
    case LOST = 'LOST';

    /** The draw result: won. */
    case WINNER = 'WINNER';

    /** Returned to the player: the round was cancelled or the player excluded. */
    case REFUNDED = 'REFUNDED';
}
