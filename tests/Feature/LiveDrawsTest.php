<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Digitonic\DrawHouse\DTO\LiveDraw;
use Digitonic\DrawHouse\DTO\LiveDraws\ImageSet;
use Digitonic\DrawHouse\DTO\LiveDraws\InstantWins;
use Digitonic\DrawHouse\DTO\LiveDraws\LiveDrawStatus;
use Digitonic\DrawHouse\Enums\PrizeCategory;
use Digitonic\DrawHouse\Enums\PrizeType;
use Digitonic\DrawHouse\Enums\RaffleEntryType;
use Digitonic\DrawHouse\Enums\RoundState;
use Digitonic\DrawHouse\Exceptions\InvalidRequestException;
use Digitonic\DrawHouse\Exceptions\NotFoundException;
use Digitonic\DrawHouse\Exceptions\ServerException;
use Digitonic\DrawHouse\Http\Requests\GetLiveDrawRequest;
use Digitonic\DrawHouse\Http\Requests\GetLiveDrawsRequest;
use Digitonic\DrawHouse\Http\Requests\GetMeRequest;
use Saloon\Http\Faking\MockClient;

beforeEach(fn () => seedTokens());

// ---------------------------------------------------------------------------
// DTO
// ---------------------------------------------------------------------------

it('parses every field of a v1.2 live draw', function () {
    $d = LiveDraw::fromArray(liveDrawFixture());

    expect($d->gameId)->toBe(40)
        ->and($d->gameName)->toBe('September Super Draw')
        ->and($d->roundId)->toBe(88)
        ->and($d->roundState)->toBe(RoundState::OPEN)
        ->and($d->entryType)->toBe(RaffleEntryType::DRAW_ONLY)
        ->and($d->asOf?->toIso8601ZuluString())->toBe('2026-09-28T12:00:00Z');

    expect($d->edition->id)->toBe(7)
        ->and($d->edition->name)->toBe('Acme September Draw')
        ->and($d->edition->slug)->toBe('acme-september-draw')
        ->and($d->edition->description)->toBe('Win a car and support a good cause.')
        ->and($d->edition->url)->toBe('https://acme.example/prize-draw/acme-september-draw');

    expect($d->images)->toHaveKeys(['hero', 'lobby-card'])
        ->and($d->images['hero'])->toBeInstanceOf(ImageSet::class)
        ->and($d->images['hero']->base)->toBe('https://cdn.example/hero.jpg')
        ->and($d->images['hero']->variants)->toBe(['2x' => 'https://cdn.example/hero@2x.jpg'])
        ->and($d->image('hero', '2x'))->toBe('https://cdn.example/hero@2x.jpg')
        ->and($d->image('hero', '3x'))->toBe('https://cdn.example/hero.jpg')
        ->and($d->image('lobby-card'))->toBe('https://cdn.example/card.jpg')
        ->and($d->images['lobby-card']->variants)->toBe([])
        ->and($d->image('missing'))->toBeNull();

    // price is a STRING; the counts are integers.
    expect($d->tickets->price)->toBe('5.00')
        ->and($d->tickets->priceAmount())->toBe(5.0)
        ->and($d->tickets->max)->toBe(10000)
        ->and($d->tickets->sold)->toBe(3120)
        ->and($d->tickets->available)->toBe(6880)
        ->and($d->tickets->maxPerPlayer)->toBe(550)
        ->and($d->tickets->soldFraction())->toBe(0.312)
        ->and($d->tickets->isSoldOut())->toBeFalse()
        ->and($d->isOpenForEntry())->toBeTrue();

    expect($d->schedule->opensAt?->toIso8601ZuluString())->toBe('2026-09-01T09:00:00Z')
        ->and($d->schedule->drawAt?->toIso8601ZuluString())->toBe('2026-09-30T20:00:00Z')
        ->and($d->schedule->completesAt?->toIso8601ZuluString())->toBe('2026-10-01T09:00:00Z')
        ->and($d->schedule->liveDrawAt)->toBeNull()
        ->and($d->nextTransition?->state)->toBe(RoundState::RESULTED)
        ->and($d->nextTransition?->scheduledAt?->toIso8601ZuluString())->toBe('2026-09-30T20:00:00Z')
        ->and($d->scheduledDrawTime?->toIso8601ZuluString())->toBe('2026-09-30T20:00:00Z');

    expect($d->prizes)->toHaveCount(2)
        ->and($d->prizes[0]->prizePoolId)->toBe(3)
        ->and($d->prizes[0]->name)->toBe('Electric Hatchback')
        ->and($d->prizes[0]->type)->toBe(PrizeType::DRAW)
        ->and($d->prizes[0]->category)->toBe(PrizeCategory::PHYSICAL)
        ->and($d->prizes[0]->imageUrl)->toBe('https://cdn.example/car.jpg')
        ->and($d->prizes[0]->totalAvailable)->toBe(1)
        ->and($d->prizes[0]->displayOrder)->toBe(1)
        ->and($d->prizes[0]->amount)->toBeNull()
        ->and($d->prizes[1]->type)->toBe(PrizeType::INSTANT)
        ->and($d->prizes[1]->category)->toBe(PrizeCategory::SITE_CREDIT)
        ->and($d->prizes[1]->amount)->toBe(10.0)
        ->and($d->prizes[1]->imageUrl)->toBeNull()
        ->and($d->drawPrizes())->toHaveCount(1)
        ->and($d->instantPrizes())->toHaveCount(1);

    expect($d->instantWins)->toBeInstanceOf(InstantWins::class)
        ->and($d->instantWins?->enabled)->toBeTrue()
        ->and($d->instantWins?->totalPrizes)->toBe(300)
        ->and($d->instantWins?->remaining)->toBe(212)
        ->and($d->instantWins?->revealed)->toBe(88)
        ->and($d->instantWins?->claimed)->toBe(80)
        ->and($d->instantWins?->totalValue)->toBe(3000.0)
        ->and($d->instantWins?->hasRemaining())->toBeTrue()
        ->and($d->instantWins?->perPrize)->toHaveCount(1)
        ->and($d->instantWins?->perPrize[0]->prizePoolId)->toBe(4)
        ->and($d->instantWins?->perPrize[0]->total)->toBe(300)
        ->and($d->instantWins?->perPrize[0]->remaining)->toBe(212)
        ->and($d->instantWins?->perPrize[0]->revealed)->toBe(88)
        ->and($d->instantWins?->perPrize[0]->claimed)->toBe(80)
        ->and($d->instantWins?->perPrize[0]->amount)->toBe(10.0);

    expect($d->liveDraw)->toBeNull()
        ->and($d->raw['gameName'])->toBe('September Super Draw');
});

it('treats absent instantWins and a null liveDraw as absent, and parses liveDraw once resulted', function () {
    $fixture = liveDrawFixture();
    unset($fixture['instantWins']);

    $open = LiveDraw::fromArray($fixture);
    expect($open->instantWins)->toBeNull()
        ->and($open->liveDraw)->toBeNull();

    $resulted = LiveDraw::fromArray(liveDrawFixture(40, [
        'roundState' => 'RESULTED',
        'liveDraw' => ['state' => 'REVEALED', 'armed' => true, 'drawTime' => '2026-09-30T20:15:00Z', 'revealed' => true, 'revealedAt' => '2026-09-30T20:16:03Z'],
    ]));

    expect($resulted->liveDraw)->toBeInstanceOf(LiveDrawStatus::class)
        ->and($resulted->liveDraw?->state)->toBe('REVEALED')
        ->and($resulted->liveDraw?->armed)->toBeTrue()
        ->and($resulted->liveDraw?->drawTime?->toIso8601ZuluString())->toBe('2026-09-30T20:15:00Z')
        ->and($resulted->liveDraw?->revealed)->toBeTrue()
        ->and($resulted->liveDraw?->revealedAt?->toIso8601ZuluString())->toBe('2026-09-30T20:16:03Z')
        ->and($resulted->isOpenForEntry())->toBeFalse();
});

// ---------------------------------------------------------------------------
// Countdown helper
// ---------------------------------------------------------------------------

it('counts down to drawAt, never scheduledDrawTime, when no live draw is armed', function () {
    $d = LiveDraw::fromArray(liveDrawFixture(40, [
        'schedule' => ['drawAt' => '2026-09-30T19:45:00Z'],
        'scheduledDrawTime' => '2026-09-30T20:00:00.000Z',
    ]));

    expect($d->isLiveDrawArmed())->toBeFalse()
        ->and($d->countdownTarget()?->toIso8601ZuluString())->toBe('2026-09-30T19:45:00Z')
        ->and($d->countdownTarget())->not->toEqual($d->scheduledDrawTime)
        ->and($d->secondsUntilDraw(CarbonImmutable::parse('2026-09-30T19:44:00Z')))->toBe(60)
        ->and($d->secondsUntilDraw(CarbonImmutable::parse('2026-09-30T19:45:00Z')))->toBe(0)
        ->and($d->secondsUntilDraw(CarbonImmutable::parse('2026-09-30T21:00:00Z')))->toBe(0);
});

it('counts down to liveDrawAt when a live draw is armed', function () {
    $d = LiveDraw::fromArray(liveDrawFixture(40, [
        'schedule' => ['drawAt' => '2026-09-30T19:45:00Z', 'liveDrawAt' => '2026-09-30T20:00:00Z'],
    ]));

    expect($d->isLiveDrawArmed())->toBeTrue()
        ->and($d->countdownTarget()?->toIso8601ZuluString())->toBe('2026-09-30T20:00:00Z')
        ->and($d->secondsUntilDraw(CarbonImmutable::parse('2026-09-30T19:45:00Z')))->toBe(900);
});

it('has no countdown when neither drawAt nor liveDrawAt is scheduled', function () {
    // array_replace (not recursive) so the fixture's drawAt is dropped entirely.
    $d = LiveDraw::fromArray(array_replace(liveDrawFixture(), ['schedule' => ['opensAt' => '2026-09-01T09:00:00Z']]));

    expect($d->countdownTarget())->toBeNull()
        ->and($d->secondsUntilDraw())->toBeNull();
});

// ---------------------------------------------------------------------------
// Requests
// ---------------------------------------------------------------------------

it('fetches live draws unauthenticated, keyed on the cached affiliate id, with default limit 20', function () {
    $mock = MockClient::global([
        GetMeRequest::class => meResponse(33),
        GetLiveDrawsRequest::class => liveDrawsResponse([liveDrawFixture(40), liveDrawFixture(41)]),
    ]);

    $draws = drawhouse()->liveDraws();

    expect($draws)->toHaveCount(2)->each->toBeInstanceOf(LiveDraw::class)
        ->and(sentRequests($mock))->toBe([GetMeRequest::class, GetLiveDrawsRequest::class]);

    $request = sentPendingRequest($mock, 1);
    expect($request->headers()->get('access-token'))->toBeNull()
        ->and($request->getUrl())->toBe('https://api.test.raffletown.co.uk/v2/partners/charity-raffles/live')
        ->and(sentQueryString($mock, 1))->toBe('affiliateId=33&limit=20');
});

it('sends editionId, repeatable state and a limit clamped to 100', function () {
    $mock = MockClient::global([
        GetLiveDrawsRequest::class => liveDrawsResponse([]),
    ]);

    drawhouse()->liveDraws(
        states: [RoundState::OPEN, RoundState::LIVE_DRAW, RoundState::OPEN],
        editionId: 7,
        limit: 500,
        affiliateId: 33,
    );

    expect(sentQueryString($mock, 0))->toBe('affiliateId=33&editionId=7&state=OPEN&state=LIVE_DRAW&limit=100')
        ->and(sentQueryString($mock, 0))->not->toContain('%5B');
});

it('rejects round states the live endpoint does not accept before making a request', function () {
    $mock = MockClient::global([]);

    expect(fn () => drawhouse()->liveDraws(states: [RoundState::SETUP], affiliateId: 33))
        ->toThrow(InvalidArgumentException::class, 'SETUP');

    expect($mock->getRecordedResponses())->toHaveCount(0);
});

it('fetches a single live draw by game id or Game', function () {
    $mock = MockClient::global([
        GetLiveDrawRequest::class => envelope(liveDrawFixture(40)),
    ]);

    $draw = drawhouse()->liveDraw(40, editionId: 7, affiliateId: 33);

    expect($draw)->toBeInstanceOf(LiveDraw::class)
        ->and($draw->gameId)->toBe(40)
        ->and(sentPendingRequest($mock, 0)->getUrl())->toBe('https://api.test.raffletown.co.uk/v2/partners/charity-raffles/live/40')
        ->and(sentQueryString($mock, 0))->toBe('affiliateId=33&editionId=7')
        ->and(sentPendingRequest($mock, 0)->headers()->get('access-token'))->toBeNull();
});

it('maps a 404 (no live draw for that game under your affiliate) to NotFoundException', function () {
    MockClient::global([
        GetLiveDrawRequest::class => envelopeError(404, 'No live draw', 404),
    ]);

    drawhouse()->liveDraw(99, affiliateId: 33);
})->throws(NotFoundException::class);

it('maps a 400 (unknown state or non-integer editionId) to InvalidRequestException', function () {
    MockClient::global([
        GetLiveDrawsRequest::class => envelopeError(7, 'editionId must be an integer', 400),
    ]);

    drawhouse()->liveDraws(affiliateId: 33);
})->throws(InvalidRequestException::class, 'editionId');

it('retries the live list once on HTTP 500 / errorCode 3 and then surfaces ServerException', function () {
    $calls = 0;

    $mock = MockClient::global([
        GetLiveDrawsRequest::class => function () use (&$calls) {
            $calls++;

            return $calls === 1 ? envelopeError(3, 'Backend failure', 500) : liveDrawsResponse([liveDrawFixture()]);
        },
    ]);

    expect(drawhouse()->liveDraws(affiliateId: 33))->toHaveCount(1)
        ->and(sentRequests($mock))->toHaveCount(2);

    MockClient::destroyGlobal();
    MockClient::global([
        GetLiveDrawsRequest::class => envelopeError(3, 'Still broken', 500),
    ]);

    expect(fn () => drawhouse()->liveDraws(affiliateId: 33))->toThrow(ServerException::class);
});
