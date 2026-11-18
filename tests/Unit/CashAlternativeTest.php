<?php

declare(strict_types=1);

use Digitonic\DrawHouse\DTO\Game;
use Digitonic\DrawHouse\DTO\LiveDraw;
use Digitonic\DrawHouse\DTO\LiveDraws\LivePrize;
use Digitonic\DrawHouse\DTO\Prize;
use Digitonic\DrawHouse\Support\CashAlternative;

/*
 * DrawHouse's own rule (`resolveDrawCashAlternative` on the brands'
 * prize-draw pages), with figures checked against what those pages show.
 */

it('is the percentage of a main draw prize\'s value, to the penny, rounded down to whole pounds', function () {
    // A PHYSICAL £230 prize at 73% shows "£167 cash alternative".
    expect(CashAlternative::for(230.0, 'PHYSICAL', 73.0))->toBe(167.0)
        // £390 at 73% shows £284.
        ->and(CashAlternative::for(390.0, 'PHYSICAL', 73.0))->toBe(284.0)
        // £180 at 73% shows £131.
        ->and(CashAlternative::for(180.0, 'PHYSICAL', 73.0))->toBe(131.0)
        // £5,385.30 at 73% is £3,931.269 → £3,931.27 → £3,931.
        ->and(CashAlternative::for(5385.3, 'PHYSICAL', 73.0))->toBe(3931.0);
});

it('covers vouchers and both physical categories', function () {
    // A VOUCHER £100 at 80% shows £80; £50 at 80% shows £40.
    expect(CashAlternative::for(100.0, 'VOUCHER', 80.0))->toBe(80.0)
        ->and(CashAlternative::for(50.0, 'voucher', 80.0))->toBe(40.0)
        ->and(CashAlternative::for(500.0, 'VOUCHER_VAT', 80.0))->toBe(400.0)
        ->and(CashAlternative::for(31595.0, 'PHYSICAL_NO_VAT', 73.0))->toBe(23064.0);
});

it('has none for cash or site credit, whatever the percentage', function (string $category) {
    // A £150 cash prize at 100% shows no cash alternative.
    expect(CashAlternative::for(150.0, $category, 100.0))->toBeNull();
})->with(['CASH', 'SITE_CREDIT', '']);

it('has none without a usable percentage or value', function (?float $amount, ?float $percentage) {
    expect(CashAlternative::for($amount, 'PHYSICAL', $percentage))->toBeNull();
})->with([
    'no percentage' => [230.0, null],
    'zero percentage' => [230.0, 0.0],
    'no value' => [null, 73.0],
    'zero value' => [0.0, 73.0],
    'under a pound' => [1.0, 73.0],
]);

it('takes any percentage above 0, as DrawHouse does', function () {
    expect(CashAlternative::for(100.0, 'PHYSICAL', 110.0))->toBe(110.0);
});

it('is only ever the main draw prize\'s: an instant prize has none', function () {
    $earbuds = LivePrize::fromArray(['name' => 'Wireless earbuds', 'type' => 'DRAW', 'category' => 'PHYSICAL', 'amount' => 230]);
    $car = LivePrize::fromArray(['name' => 'Hatchback car', 'type' => 'INSTANT', 'category' => 'PHYSICAL_NO_VAT', 'amount' => 31595]);
    $catalogueCar = Prize::fromArray(['name' => 'Hatchback car', 'type' => 'INSTANT', 'category' => 'PHYSICAL_NO_VAT', 'amount' => '31595']);

    expect($earbuds->cashAlternative(73.0))->toBe(167.0)
        ->and($earbuds->cashAlternative(null))->toBeNull()
        ->and($car->cashAlternative(73.0))->toBeNull()
        ->and($catalogueCar->cashAlternative(73.0))->toBeNull();
});

it('picks a live draw\'s first DRAW prize, with the edition\'s category', function () {
    $overridden = LiveDraw::fromArray(['gameId' => 1, 'prizes' => [
        // The live feed applies the edition: a prize the base game lists as CASH arrives as PHYSICAL.
        ['name' => 'Smart glasses', 'type' => 'DRAW', 'category' => 'PHYSICAL', 'amount' => 390],
    ]]);
    $hybrid = LiveDraw::fromArray(['gameId' => 2, 'prizes' => [
        ['name' => '£5 site credit', 'type' => 'INSTANT', 'category' => 'SITE_CREDIT', 'amount' => 5],
        ['name' => 'Ninja Air Fryer', 'type' => 'DRAW', 'category' => 'PHYSICAL', 'amount' => 250],
    ]]);
    $instantOnly = LiveDraw::fromArray(['gameId' => 3, 'prizes' => [
        ['name' => 'Hatchback car', 'type' => 'INSTANT', 'category' => 'PHYSICAL_NO_VAT', 'amount' => 31595],
    ]]);
    $cash = LiveDraw::fromArray(['gameId' => 4, 'prizes' => [
        ['name' => '£250 Cash', 'type' => 'DRAW', 'category' => 'CASH', 'amount' => 250],
    ]]);

    expect($overridden->cashAlternative(73.0))->toBe(284.0)
        ->and($hybrid->cashAlternative(73.0))->toBe(182.0)
        ->and($instantOnly->cashAlternative(73.0))->toBeNull()
        ->and($cash->cashAlternative(75.0))->toBeNull();
});

it('applies the game\'s own percentage', function () {
    $game = Game::fromArray(gameFixture());
    $prize = Prize::fromArray(['name' => 'TV', 'type' => 'DRAW', 'category' => 'PHYSICAL', 'amount' => 500]);

    // The fixture's game offers 80%.
    expect($game->fields->physicalToCashPercentage)->toBe(80.0)
        ->and($game->cashAlternative($prize))->toBe(400.0);
});
