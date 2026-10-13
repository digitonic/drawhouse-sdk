<?php

declare(strict_types=1);

namespace Digitonic\DrawHouse\Enums;

enum RoundState: string
{
    case SETUP = 'SETUP';
    case READY = 'READY';
    case OPEN = 'OPEN';
    case RESULTED = 'RESULTED';
    case LIVE_DRAW = 'LIVE_DRAW';
    case COMPLETED = 'COMPLETED';
    case CANCELLED = 'CANCELLED';
    case VOID = 'VOID';

    /**
     * Rounds in which entries can still be sold or a draw is in flight.
     */
    public function isActive(): bool
    {
        return $this === self::OPEN || $this === self::LIVE_DRAW;
    }
}
