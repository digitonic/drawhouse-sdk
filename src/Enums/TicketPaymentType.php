<?php

declare(strict_types=1);

namespace Digitonic\DrawHouse\Enums;

/**
 * `paymentType` of a ticket: how it was paid for.
 */
enum TicketPaymentType: string
{
    /** Card or wallet cash. */
    case DEBIT = 'DEBIT';

    /** Site credit. */
    case CREDIT = 'CREDIT';

    /** Free postal entry. */
    case POSTAL = 'POSTAL';
}
