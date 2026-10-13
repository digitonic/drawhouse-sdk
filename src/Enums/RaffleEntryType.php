<?php

declare(strict_types=1);

namespace Digitonic\DrawHouse\Enums;

/**
 * `fields.raffleEntryType` on a game.
 */
enum RaffleEntryType: string
{
    case DRAW_ONLY = 'DRAW_ONLY';
    case INSTANT_ONLY = 'INSTANT_ONLY';
    case INSTANT_AND_DRAW = 'INSTANT+DRAW';

    public function hasDraw(): bool
    {
        return $this !== self::INSTANT_ONLY;
    }

    public function hasInstantWins(): bool
    {
        return $this !== self::DRAW_ONLY;
    }
}
