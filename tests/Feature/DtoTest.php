<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Digitonic\DrawHouse\DTO\Game;
use Digitonic\DrawHouse\DTO\Player;
use Digitonic\DrawHouse\DTO\Round;
use Digitonic\DrawHouse\DTO\Ticket;
use Digitonic\DrawHouse\Enums\GameState;
use Digitonic\DrawHouse\Enums\PrizeCategory;
use Digitonic\DrawHouse\Enums\PrizeType;
use Digitonic\DrawHouse\Enums\RaffleEntryType;
use Digitonic\DrawHouse\Enums\RoundState;

it('normalises the string-typed game fields and round ticket price', function () {
    $game = Game::fromArray(gameFixture());

    expect($game->id)->toBe(101)
        ->and($game->state)->toBe(GameState::INPROGRESS)
        ->and($game->currentRoundId)->toBe(9001)
        ->and($game->isParticipating)->toBeTrue();

    $f = $game->fields;
    expect($f->raffleEntryType)->toBe(RaffleEntryType::INSTANT_AND_DRAW)
        ->and($f->raffleEntryType?->hasInstantWins())->toBeTrue()
        ->and($f->revealMechanic)->toBe('SCAN')
        ->and($f->hasCashPrize)->toBeTrue()
        ->and($f->maxTickets)->toBe(550)
        ->and($f->maxTicketsPerPlayer)->toBe(550)
        ->and($f->defaultCheckoutTicketCount)->toBe(10)
        ->and($f->entriesCount)->toBe(0)
        ->and($f->scheduledDrawTime?->toIso8601ZuluString())->toBe('2026-09-28T19:00:00Z')
        ->and($f->rafflePrizePercentage)->toBe(50.0)
        ->and($f->physicalToCashPercentage)->toBe(80.0)
        ->and($f->cashToSiteCreditPercentage)->toBe(110.0)
        ->and($f->isRingFenced)->toBeTrue()
        ->and($f->raw['maxTickets'])->toBe('550');

    $round = $game->rounds[0];
    expect($round)->toBeInstanceOf(Round::class)
        ->and($round->roundNumber)->toBe(1)
        ->and($round->state)->toBe(RoundState::OPEN)
        ->and($round->isActive())->toBeTrue()
        ->and($round->ticketPrice)->toBe(0.2)
        ->and($round->raw['ticketPrice'])->toBe('0.20')
        ->and($round->schedule->opensAt)->toBeInstanceOf(CarbonImmutable::class)
        ->and($round->schedule->drawAt?->toIso8601ZuluString())->toBe('2026-09-28T18:45:00Z')
        ->and($round->schedule->liveDrawAt?->toIso8601ZuluString())->toBe('2026-09-28T19:00:00Z')
        ->and($round->schedule->completesAt?->toIso8601ZuluString())->toBe('2026-09-28T19:30:00Z')
        ->and($round->schedule->cancelledAt)->toBeNull()
        ->and($round->nextTransition?->toState)->toBe('RESULTED')
        ->and($round->nextTransition?->state)->toBe(RoundState::RESULTED)
        ->and($round->nextTransition?->scheduledAt?->toIso8601ZuluString())->toBe('2026-09-28T18:45:00Z');

    expect($round->prizes)->toHaveCount(2)
        ->and($round->prizes[0]->type)->toBe(PrizeType::DRAW)
        ->and($round->prizes[0]->category)->toBe(PrizeCategory::CASH)
        ->and($round->prizes[0]->amount)->toBe(550.0)
        ->and($round->prizes[1]->type)->toBe(PrizeType::INSTANT_SITE_CREDIT)
        ->and($round->prizes[1]->type?->isInstant())->toBeTrue()
        ->and($round->prizes[1]->totalAvailable)->toBe(50)
        ->and($round->prizes[1]->soldCount)->toBe(12)
        ->and($round->prizes[1]->remaining())->toBe(38);

    expect($game->currentRound())->toBe($round)
        ->and($game->editions)->toHaveCount(2)
        ->and($game->myEdition()?->id)->toBe(501)
        ->and($game->myEdition()?->slug)->toBe('acme-weekly')
        ->and($game->editions[1]->isMine)->toBeFalse()
        ->and($game->editions[1]->affiliateId)->toBe(33);
});

it('treats omitted optional fields as absent rather than failing', function () {
    $fixture = gameFixture();
    unset($fixture['currentRoundId'], $fixture['editions']);
    unset($fixture['fields']['physicalToCashPercentage'], $fixture['fields']['physicalToSiteCreditPercentage'], $fixture['fields']['cashToSiteCreditPercentage']);
    unset($fixture['rounds'][0]['nextTransition'], $fixture['rounds'][0]['prizes'], $fixture['rounds'][0]['ticketPrice']);
    unset($fixture['rounds'][0]['schedule']['liveDrawAt'], $fixture['rounds'][0]['schedule']['completesAt']);
    $fixture['rounds'][0]['state'] = 'RESULTED';

    $game = Game::fromArray($fixture);

    expect($game->currentRoundId)->toBeNull()
        ->and($game->currentRound())->toBeNull()
        ->and($game->editions)->toBe([])
        ->and($game->myEdition())->toBeNull()
        ->and($game->fields->physicalToCashPercentage)->toBeNull()
        ->and($game->fields->maxTickets)->toBe(550)
        ->and($game->rounds[0]->ticketPrice)->toBeNull()
        ->and($game->rounds[0]->nextTransition)->toBeNull()
        ->and($game->rounds[0]->prizes)->toBe([])
        ->and($game->rounds[0]->isActive())->toBeFalse()
        ->and($game->rounds[0]->schedule->liveDrawAt)->toBeNull()
        ->and($game->rounds[0]->schedule->opensAt)->not->toBeNull();
});

it('tolerates a game with an empty rounds array and no fields', function () {
    $game = Game::fromArray(['id' => 7, 'name' => 'Bare', 'state' => 'SETUP', 'isParticipating' => false, 'fields' => [], 'rounds' => []]);

    expect($game->rounds)->toBe([])
        ->and($game->fields->raffleEntryType)->toBeNull()
        ->and($game->state)->toBe(GameState::SETUP);
});

it('keeps unknown extra fields in raw so richer data is not lost', function () {
    $game = Game::fromArray(gameFixture(9, ['charityName' => 'Cancer Research UK', 'fields' => ['maxTickets' => '10', 'drawDay' => 'Friday']]));

    expect($game->raw['charityName'])->toBe('Cancer Research UK')
        ->and($game->fields->string('drawDay'))->toBe('Friday')
        ->and($game->fields->int('maxTickets'))->toBe(10)
        ->and($game->fields->int('missing'))->toBeNull();
});

it('parses every documented round state and every documented enum value', function () {
    foreach (['SETUP', 'READY', 'OPEN', 'RESULTED', 'LIVE_DRAW', 'COMPLETED', 'CANCELLED', 'VOID'] as $state) {
        expect(RoundState::from($state)->value)->toBe($state);
    }

    expect(RaffleEntryType::from('INSTANT+DRAW'))->toBe(RaffleEntryType::INSTANT_AND_DRAW)
        ->and(RaffleEntryType::from('DRAW_ONLY')->hasInstantWins())->toBeFalse()
        ->and(PrizeType::from('INSTANT_PHYSICAL')->isInstant())->toBeTrue()
        ->and(PrizeCategory::from('SITE_CREDIT'))->toBe(PrizeCategory::SITE_CREDIT)
        ->and(RoundState::LIVE_DRAW->isActive())->toBeTrue()
        ->and(RoundState::VOID->isActive())->toBeFalse();
});

it('parses the v1.2 prize type and category values, and keeps the v1.0 INSTANT_* spellings parsing', function () {
    expect(PrizeType::from('INSTANT')->isInstant())->toBeTrue()
        ->and(PrizeType::INSTANT->isLegacy())->toBeFalse()
        ->and(PrizeType::DRAW->isDraw())->toBeTrue()
        ->and(PrizeType::INSTANT_SITE_CREDIT->isLegacy())->toBeTrue()
        ->and(PrizeType::INSTANT_SITE_CREDIT->isInstant())->toBeTrue()
        ->and(PrizeCategory::from('VOUCHER'))->toBe(PrizeCategory::VOUCHER);

    $game = Game::fromArray(gameFixture(9, ['rounds' => [array_replace(gameFixture()['rounds'][0], ['prizes' => [
        ['name' => '£20 voucher', 'type' => 'INSTANT', 'category' => 'VOUCHER', 'amount' => 20, 'totalAvailable' => 10, 'soldCount' => 1],
    ]])]]));

    expect($game->rounds[0]->prizes[0]->type)->toBe(PrizeType::INSTANT)
        ->and($game->rounds[0]->prizes[0]->category)->toBe(PrizeCategory::VOUCHER);
});

it('parses unknown enum values to null instead of throwing', function () {
    $game = Game::fromArray(gameFixture(9, ['state' => 'SOMETHING_NEW']));

    expect($game->state)->toBeNull()
        ->and($game->raw['state'])->toBe('SOMETHING_NEW');
});

it('parses enum values case-insensitively', function () {
    $game = Game::fromArray(gameFixture(9, ['state' => 'inprogress']));

    expect($game->state)->toBe(GameState::INPROGRESS);
});

it('parses the player date formats into CarbonImmutable', function () {
    $player = Player::fromArray(playerFixture());

    expect($player->registrationDate)->toBeInstanceOf(CarbonImmutable::class)
        ->and($player->registrationDate?->toDateString())->toBe('2026-08-01')
        ->and($player->dateOfBirth?->toDateString())->toBe('1999-01-01')
        ->and($player->dateOfBirth?->timezoneName)->toBe('UTC');
});

it('accepts a numeric registrationDate and rejects garbage dates quietly', function () {
    $player = Player::fromArray(['id' => 1, 'registrationDate' => 20260801, 'dateOfBirth' => 'not-a-date']);

    expect($player->registrationDate?->toDateString())->toBe('2026-08-01')
        ->and($player->dateOfBirth)->toBeNull();
});

it('normalises empty ticket UTM strings to null and keeps populated ones', function () {
    $ticket = Ticket::fromArray(array_replace(ticketFixture(), ['utmSource' => 'facebook', 'utmMedium' => 'cpc', 'utmCampaign' => '']));

    expect($ticket->utmSource)->toBe('facebook')
        ->and($ticket->utmMedium)->toBe('cpc')
        ->and($ticket->utmCampaign)->toBeNull()
        ->and($ticket->hasUtm())->toBeTrue()
        ->and($ticket->raw['utmCampaign'])->toBe('');
});
