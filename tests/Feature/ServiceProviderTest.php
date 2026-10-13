<?php

declare(strict_types=1);

use Digitonic\DrawHouse\DrawHouse;
use Digitonic\DrawHouse\Facades\DrawHouse as DrawHouseFacade;
use Digitonic\DrawHouse\Http\DrawHouseConnector;

it('binds the client as a singleton', function () {
    expect(app(DrawHouse::class))->toBeInstanceOf(DrawHouse::class)
        ->and(app(DrawHouse::class))->toBe(app(DrawHouse::class))
        ->and(app('drawhouse'))->toBe(app(DrawHouse::class));
});

it('merges the package config', function () {
    expect(config('drawhouse.base_url'))->toBe('https://api.test.raffletown.co.uk')
        ->and(config('drawhouse.cache.prefix'))->toBe('drawhouse')
        ->and(config('drawhouse.timeout'))->toBe(30);
});

it('resolves the connector with the configured base url', function () {
    expect(app(DrawHouseConnector::class)->resolveBaseUrl())->toBe('https://api.test.raffletown.co.uk');
});

it('exposes a facade', function () {
    expect(DrawHouseFacade::getFacadeRoot())->toBeInstanceOf(DrawHouse::class);
});
