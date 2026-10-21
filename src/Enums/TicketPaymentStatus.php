<?php

declare(strict_types=1);

namespace Digitonic\DrawHouse\Enums;

/**
 * `paymentStatus` of a ticket. SUCCEEDED is a real purchase; the rest are
 * abandoned, failed or still-pending checkouts.
 */
enum TicketPaymentStatus: string
{
    case NOTATTEMPTED = 'NOTATTEMPTED';
    case PENDING = 'PENDING';
    case FAILED = 'FAILED';
    case SUCCEEDED = 'SUCCEEDED';
    case REFERRED = 'REFERRED';
}
