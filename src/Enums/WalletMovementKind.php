<?php

declare(strict_types=1);

namespace Digitonic\DrawHouse\Enums;

/**
 * Why a wallet balance moved, on `wallet.credited` / `wallet.debited`. Where
 * a value also exists on the money feed (MoneyKind) it means the same thing.
 */
enum WalletMovementKind: string
{
    case DEPOSIT = 'DEPOSIT';
    case ADMIN_CREDIT = 'ADMIN_CREDIT';
    case ADMIN_DEBIT = 'ADMIN_DEBIT';
    case PURCHASE = 'PURCHASE';
    case REFUND = 'REFUND';
    case PRIZE_PAYOUT = 'PRIZE_PAYOUT';
    case PRIZE_CONVERSION = 'PRIZE_CONVERSION';
    case WITHDRAWAL = 'WITHDRAWAL';
    case BONUS = 'BONUS';
}
