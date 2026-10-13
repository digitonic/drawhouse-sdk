<?php

declare(strict_types=1);

namespace Digitonic\DrawHouse\Enums;

enum GameState: string
{
    case SETUP = 'SETUP';
    case READY = 'READY';
    case INPROGRESS = 'INPROGRESS';
    case REPORTED = 'REPORTED';
    case RESULTED = 'RESULTED';
    case COMPLETED = 'COMPLETED';
    case ARCHIVED = 'ARCHIVED';
    case DELETED = 'DELETED';
    case CANCELLED = 'CANCELLED';

    /**
     * States the games endpoint returns when no `state` filter is passed.
     * SETUP, ARCHIVED, DELETED and CANCELLED must be requested explicitly.
     *
     * @return list<self>
     */
    public static function defaults(): array
    {
        return [self::READY, self::INPROGRESS, self::REPORTED, self::RESULTED, self::COMPLETED];
    }
}
