<?php

declare(strict_types=1);

use Digitonic\DrawHouse\DrawHouse;
use Digitonic\DrawHouse\DTO\Game;
use Digitonic\DrawHouse\Enums\GameState;
use Digitonic\DrawHouse\Enums\Participation;
use Digitonic\DrawHouse\Exceptions\DrawHouseException;
use Digitonic\DrawHouse\Http\Requests\GetGamesRequest;
use Digitonic\DrawHouse\Http\Requests\GetMeRequest;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\PendingRequest;

beforeEach(fn () => seedTokens());

it('fetches a page of games unauthenticated, keyed on the cached affiliate id', function () {
    $mock = MockClient::global([
        GetMeRequest::class => meResponse(33),
        GetGamesRequest::class => gamesResponse([gameFixture(1), gameFixture(2)]),
    ]);

    $games = drawhouse()->games();

    expect($games)->toHaveCount(2)->each->toBeInstanceOf(Game::class)
        ->and(sentRequests($mock))->toBe([GetMeRequest::class, GetGamesRequest::class]);

    $request = sentPendingRequest($mock, 1);
    expect($request->headers()->get('access-token'))->toBeNull()
        ->and($request->getUrl())->toBe('https://api.test.raffletown.co.uk/v2/partners/charity-raffles/games')
        ->and(sentQueryString($mock, 1))->toBe('affiliateId=33&limit=50&offset=0');
});

it('only calls /v2/partners/me once and caches the affiliate id as an integer', function () {
    $mock = MockClient::global([
        GetMeRequest::class => meResponse(20),
        GetGamesRequest::class => gamesResponse([]),
    ]);

    drawhouse()->games();
    drawhouse()->games();
    expect(drawhouse()->affiliateId())->toBe(20);

    expect(array_count_values(sentRequests($mock))[GetMeRequest::class])->toBe(1);
});

it('uses a configured affiliate id without calling /v2/partners/me', function () {
    config()->set('drawhouse.affiliate_id', '44');
    app()->forgetInstance(DrawHouse::class);

    $mock = MockClient::global([
        GetGamesRequest::class => gamesResponse([]),
    ]);

    drawhouse()->games();

    expect(sentRequests($mock))->toBe([GetGamesRequest::class])
        ->and(sentQueryString($mock, 0))->toStartWith('affiliateId=44&');
});

it('sends participation in lowercase and state as a REPEATED key, never brackets or a comma list', function () {
    $mock = MockClient::global([
        GetGamesRequest::class => gamesResponse([]),
    ]);

    drawhouse()->games(
        participation: Participation::AVAILABLE,
        states: [GameState::READY, GameState::INPROGRESS, GameState::READY],
        limit: 20,
        offset: 20,
        affiliateId: 33,
    );

    $query = sentQueryString($mock, 0);

    // The documented wire format (order aside):
    // ?affiliateId=33&participation=available&state=READY&state=INPROGRESS
    expect($query)->toBe('affiliateId=33&limit=20&offset=20&participation=available&state=READY&state=INPROGRESS')
        ->and($query)->not->toContain('%5B')
        ->and($query)->not->toContain('[')
        ->and($query)->not->toContain(',');
});

it('omits state entirely so the API applies its documented defaults', function () {
    $mock = MockClient::global([
        GetGamesRequest::class => gamesResponse([]),
    ]);

    drawhouse()->games(affiliateId: 33);

    expect(sentQueryString($mock, 0))->not->toContain('state')
        ->and(GameState::defaults())->toBe([
            GameState::READY, GameState::INPROGRESS, GameState::REPORTED, GameState::RESULTED, GameState::COMPLETED,
        ]);
});

it('walks every page and stops only on an empty page, not a short one', function () {
    // Paging is applied before the participation filter, so page 2 being
    // shorter than the page size must not end the iteration.
    $pages = [
        0 => [gameFixture(1), gameFixture(2), gameFixture(3)],
        3 => [gameFixture(4)],
        6 => [gameFixture(5), gameFixture(6)],
        9 => [],
    ];

    $mock = MockClient::global([
        GetGamesRequest::class => fn (PendingRequest $request) => gamesResponse($pages[$request->query()->get('offset')]),
    ]);

    $ids = [];
    foreach (drawhouse()->allGames(Participation::JOINED, pageSize: 3, affiliateId: 33) as $game) {
        $ids[] = $game->id;
    }

    expect($ids)->toBe([1, 2, 3, 4, 5, 6])
        ->and(sentRequests($mock))->toHaveCount(4);

    $offsets = array_map(fn ($r) => $r->getPendingRequest()->query()->get('offset'), $mock->getRecordedResponses());
    expect($offsets)->toBe([0, 3, 6, 9]);
});

it('is lazy: does not fetch the next page until the current one is consumed', function () {
    $mock = MockClient::global([
        GetGamesRequest::class => fn (PendingRequest $request) => gamesResponse(
            $request->query()->get('offset') === 0 ? [gameFixture(1)] : []
        ),
    ]);

    $generator = drawhouse()->allGames(pageSize: 1, affiliateId: 33);

    expect($mock->getRecordedResponses())->toHaveCount(0);

    $generator->current();

    expect($mock->getRecordedResponses())->toHaveCount(1);
});

it('retries the games call once on the documented transient HTTP 500 / errorCode 3', function () {
    $calls = 0;

    $mock = MockClient::global([
        GetGamesRequest::class => function () use (&$calls) {
            $calls++;

            return $calls === 1
                ? envelopeError(3, 'Server failure while assembling the list', 500)
                : gamesResponse([gameFixture(1)]);
        },
    ]);

    $games = drawhouse()->games(affiliateId: 33);

    expect($games)->toHaveCount(1)
        ->and(sentRequests($mock))->toHaveCount(2);
});

it('does not retry the games call on the documented 400 / errorCode 7 parameter error', function () {
    $mock = MockClient::global([
        GetGamesRequest::class => envelopeError(7, 'PartnerListCharityRaffleGames - handler - affiliateId query param is required', 400),
    ]);

    try {
        drawhouse()->games(affiliateId: 33);
        $this->fail('Expected DrawHouseException');
    } catch (DrawHouseException $e) {
        expect($e->errorCode)->toBe(7)
            ->and($e->status)->toBe(400)
            ->and(sentRequests($mock))->toHaveCount(1);
    }
});

it('gives up after a second transient failure', function () {
    MockClient::global([
        GetGamesRequest::class => envelopeError(3, 'Still broken', 500),
    ]);

    drawhouse()->games(affiliateId: 33);
})->throws(DrawHouseException::class, 'Still broken');
