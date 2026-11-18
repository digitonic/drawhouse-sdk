<?php

declare(strict_types=1);

use Digitonic\DrawHouse\DTO\Game;
use Digitonic\DrawHouse\DTO\LiveDraws\LivePrize;
use Digitonic\DrawHouse\DTO\Prize;
use Digitonic\DrawHouse\Support\CashAlternative;

/*
 * The storefront's rule (App\Catalogue\Prize::fromPlatform()), checked
 * against PrizeJoe's live draws on 18 Nov 2026.
 */

it('is the game\'s share of a physical prize\'s value, rounded down to whole pounds', function () {
    // "Win Apple AirPods Pro 3 (or £167 cash alternative)!": £230 at 73%.
    expect(CashAlternative::for(230.0, 'PHYSICAL', 73.0))->toBe(167.0)
        // Apple Watch Series 12: £380 at 73% is £277.40.
        ->and(CashAlternative::for(380.0, 'PHYSICAL', 73.0))->toBe(277.0)
        // DrawHouse's PHYSICAL_NO_VAT counts as physical, as on the storefront.
        ->and(CashAlternative::for(31595.0, 'PHYSICAL_NO_VAT', 73.0))->toBe(23064.0)
        ->and(CashAlternative::for(100.0, ' physical ', 100.0))->toBe(100.0);
});

it('has none for vouchers, cash or site credit', function (string $category) {
    expect(CashAlternative::for(100.0, $category, 80.0))->toBeNull();
})->with(['VOUCHER', 'CASH', 'SITE_CREDIT', '']);

it('has none without a usable percentage or value', function (?float $amount, ?string $category, ?float $percentage) {
    expect(CashAlternative::for($amount, $category, $percentage))->toBeNull();
})->with([
    'no percentage' => [230.0, 'PHYSICAL', null],
    'zero percentage' => [230.0, 'PHYSICAL', 0.0],
    'over 100%' => [230.0, 'PHYSICAL', 110.0],
    'no value' => [null, 'PHYSICAL', 73.0],
    'zero value' => [0.0, 'PHYSICAL', 73.0],
    'under a pound' => [1.0, 'PHYSICAL', 73.0],
    'no category' => [230.0, null, 73.0],
]);

it('reads the raw category on prizes, so PHYSICAL_NO_VAT counts though the enum does not know it', function () {
    $car = Prize::fromArray(['name' => 'MINI Countryman C', 'type' => 'INSTANT', 'category' => 'PHYSICAL_NO_VAT', 'amount' => '31595']);
    $airpods = LivePrize::fromArray(['name' => 'Apple Airpods Pro 3', 'type' => 'DRAW', 'category' => 'PHYSICAL', 'amount' => 230]);
    $voucher = LivePrize::fromArray(['name' => '£100 Voucher', 'type' => 'DRAW', 'category' => 'VOUCHER', 'amount' => 100]);

    expect($car->category)->toBeNull()
        ->and($car->cashAlternative(73.0))->toBe(23064.0)
        ->and($airpods->cashAlternative(73.0))->toBe(167.0)
        ->and($airpods->cashAlternative(null))->toBeNull()
        ->and($voucher->cashAlternative(80.0))->toBeNull();
});

it('applies the game\'s own percentage', function () {
    $game = Game::fromArray(gameFixture());
    $prize = Prize::fromArray(['name' => 'TV', 'type' => 'DRAW', 'category' => 'PHYSICAL', 'amount' => 500]);

    // The fixture's game offers 80% cash for physical prizes.
    expect($game->fields->physicalToCashPercentage)->toBe(80.0)
        ->and($game->cashAlternative($prize))->toBe(400.0);
});
