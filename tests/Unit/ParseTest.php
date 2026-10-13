<?php

declare(strict_types=1);

use Digitonic\DrawHouse\Support\Parse;

it('coerces numeric strings and numbers', function () {
    expect(Parse::float('0.20'))->toBe(0.2)
        ->and(Parse::float(0.2))->toBe(0.2)
        ->and(Parse::float('abc'))->toBeNull()
        ->and(Parse::float(null))->toBeNull()
        ->and(Parse::int('550'))->toBe(550)
        ->and(Parse::int(550.0))->toBe(550)
        ->and(Parse::int(''))->toBeNull();
});

it('coerces boolean strings', function () {
    expect(Parse::bool('true'))->toBeTrue()
        ->and(Parse::bool('false'))->toBeFalse()
        ->and(Parse::bool('1'))->toBeTrue()
        ->and(Parse::bool(0))->toBeFalse()
        ->and(Parse::bool(true))->toBeTrue()
        ->and(Parse::bool('maybe'))->toBeNull();
});

it('parses RFC 3339 timestamps to UTC', function () {
    $dt = Parse::dateTime('2026-09-22T10:00:00+01:00');

    expect($dt?->toIso8601ZuluString())->toBe('2026-09-22T09:00:00Z')
        ->and(Parse::dateTime('garbage'))->toBeNull()
        ->and(Parse::dateTime(null))->toBeNull();
});

it('rejects impossible calendar dates instead of rolling them forward', function () {
    expect(Parse::date('2023-02-29'))->toBeNull()
        ->and(Parse::date('2026-13-01'))->toBeNull()
        ->and(Parse::date('2026-02-30'))->toBeNull()
        ->and(Parse::compactDate('20230229'))->toBeNull()
        ->and(Parse::compactDate(20260931))->toBeNull()
        ->and(Parse::date('2024-02-29')?->toDateString())->toBe('2024-02-29')
        ->and(Parse::compactDate('20240229')?->toDateString())->toBe('2024-02-29');
});

it('extracts item lists from bare arrays, keyed wrappers and single objects', function () {
    expect(Parse::items([['a' => 1], ['a' => 2]]))->toHaveCount(2)
        ->and(Parse::items(['games' => [['a' => 1]]], 'games'))->toHaveCount(1)
        ->and(Parse::items(['id' => 'single']))->toBe([['id' => 'single']])
        ->and(Parse::items('nope'))->toBe([])
        ->and(Parse::items([['a' => 1], 'not-an-item']))->toHaveCount(1);
});
