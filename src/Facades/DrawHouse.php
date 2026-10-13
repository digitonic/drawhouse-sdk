<?php

declare(strict_types=1);

namespace Digitonic\DrawHouse\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @mixin \Digitonic\DrawHouse\DrawHouse
 *
 * @see \Digitonic\DrawHouse\DrawHouse
 */
final class DrawHouse extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Digitonic\DrawHouse\DrawHouse::class;
    }
}
