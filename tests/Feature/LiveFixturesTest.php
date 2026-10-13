<?php

declare(strict_types=1);

use Digitonic\DrawHouse\DTO\Balances;
use Digitonic\DrawHouse\DTO\DailyReport;
use Digitonic\DrawHouse\DTO\Game;
use Digitonic\DrawHouse\DTO\LiveDraw;
use Digitonic\DrawHouse\DTO\Partner;
use Digitonic\DrawHouse\DTO\PlayerDetail;
use Digitonic\DrawHouse\DTO\Ticket;
use Digitonic\DrawHouse\DTO\UtmBreakdown;
use Digitonic\DrawHouse\Exceptions\PartnerNotLiveException;
use Digitonic\DrawHouse\Http\Requests\GetAffiliateReportsRequest;
use Digitonic\DrawHouse\Http\Requests\GetBalancesRequest;
use Digitonic\DrawHouse\Http\Requests\GetGamesRequest;
use Digitonic\DrawHouse\Http\Requests\GetLiveDrawRequest;
use Digitonic\DrawHouse\Http\Requests\GetLiveDrawsRequest;
use Digitonic\DrawHouse\Http\Requests\GetMeRequest;
use Digitonic\DrawHouse\Http\Requests\GetPlayerRequest;
use Digitonic\DrawHouse\Http\Requests\GetPlayersRequest;
use Digitonic\DrawHouse\Http\Requests\GetReportUtmsRequest;
use Digitonic\DrawHouse\Http\Requests\GetTicketsRequest;
use Digitonic\DrawHouse\Support\ResponseShape;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

/*
 * Replays tests/Fixtures/live/*.json through the SDK. The committed files are
 * SYNTHETIC: they have the keys and types `php artisan drawhouse:fixtures
 * --record` writes, with fake, already-redacted values. They are the canary:
 * when DrawHouse change a response and the fixtures are re-recorded, a DTO
 * that stops parsing fails here. A missing fixture skips its test.
 *
 * `drawhouse:fixtures --check` is the other half: it diffs the LIVE shape
 * against these files without touching them.
 */

beforeEach(fn () => seedTokens());

/**
 * @return array{status: int, body: array<string, mixed>, recordedAt: string}|null
 */
function liveFixture(string $name): ?array
{
    $file = __DIR__.'/../Fixtures/live/'.$name.'.json';

    if (! is_file($file)) {
        return null;
    }

    return json_decode((string) file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
}

function liveResponse(string $name): MockResponse
{
    $fixture = liveFixture($name) ?? test()->markTestSkipped("No recorded fixture tests/Fixtures/live/{$name}.json; run `php artisan drawhouse:fixtures --record`.");

    return MockResponse::make($fixture['body'], $fixture['status']);
}

it('contains no PII or tokens in any recorded fixture', function () {
    $files = glob(__DIR__.'/../Fixtures/live/*.json') ?: [];

    if ($files === []) {
        $this->markTestSkipped('No recorded fixtures.');
    }

    foreach ($files as $file) {
        $json = (string) file_get_contents($file);
        $body = json_decode($json, true, 512, JSON_THROW_ON_ERROR)['body'];

        // Redacting an already-redacted body must be a no-op.
        expect(ResponseShape::redact($body))->toBe($body, basename($file).' is not fully redacted')
            ->and($json)->not->toContain('"pooledRevShare": 6')
            ->and($json)->not->toMatch('/[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[a-z]{2,}/i', basename($file).' contains an email address')
            ->and($json)->not->toMatch('/\+?44\d{9,10}|07\d{9}/', basename($file).' contains a UK phone number');
    }
});

it('parses the recorded partner profile', function () {
    MockClient::global([GetMeRequest::class => liveResponse('me')]);

    $me = drawhouse()->me();

    expect($me)->toBeInstanceOf(Partner::class)
        ->and($me->status)->not->toBeNull()
        ->and($me->playerId)->not->toBeNull()
        ->and($me->affiliateId)->not->toBeNull();
});

it('parses the recorded balances', function () {
    MockClient::global([GetBalancesRequest::class => liveResponse('balances')]);

    $balances = drawhouse()->balances();

    expect($balances)->toBeInstanceOf(Balances::class)
        ->and($balances->cashBalance)->not->toBeNull()
        ->and($balances->siteCreditBalance)->not->toBeNull()
        ->and($balances->vaultKeyBalance)->not->toBeNull();
});

it('parses the recorded daily report snapshot', function () {
    MockClient::global([GetAffiliateReportsRequest::class => liveResponse('daily-reports')]);

    $reports = drawhouse()->dailyReports('2026-01-14', '2026-01-14');

    expect($reports)->not->toBeEmpty()
        ->and($reports[0])->toBeInstanceOf(DailyReport::class)
        ->and($reports[0]->id)->not->toBeNull()
        ->and($reports[0]->registrations)->not->toBeNull()
        ->and($reports[0]->played)->not->toBeNull()
        ->and($reports[0]->active)->not->toBeNull()
        ->and($reports[0]->internalMarketable)->not->toBeNull()
        ->and($reports[0]->externalMarketable)->not->toBeNull()
        ->and($reports[0]->startAt)->not->toBeNull();
});

it('parses the recorded UTM breakdown', function () {
    MockClient::global([GetReportUtmsRequest::class => liveResponse('report-utms')]);

    $utms = drawhouse()->utms(1);

    expect($utms)->toBeArray()->each->toBeInstanceOf(UtmBreakdown::class);

    foreach ($utms as $utm) {
        expect($utm->source)->not->toBeNull()
            ->and($utm->count)->not->toBeNull()
            ->and($utm->registered)->not->toBeNull();
    }
});

it('parses the recorded players page, or the partner_not_live gate', function () {
    $fixture = liveFixture('players-page') ?? $this->markTestSkipped('No recorded players-page fixture.');

    MockClient::global([GetPlayersRequest::class => MockResponse::make($fixture['body'], $fixture['status'])]);

    if ($fixture['status'] === 403) {
        expect(fn () => drawhouse()->playersPage())->toThrow(PartnerNotLiveException::class);

        return;
    }

    $page = drawhouse()->playersPage();

    expect($page->items)->each->toBeInstanceOf(PlayerDetail::class);

    foreach ($page->items as $player) {
        expect($player->id)->not->toBeNull()
            ->and($player->accountStatus)->not->toBeNull('accountStatus did not parse: '.json_encode($player->raw['accountStatus'] ?? null))
            ->and($player->registeredAt)->not->toBeNull()
            ->and($player->updatedAt)->not->toBeNull()
            ->and($player->marketable)->not->toBeNull()
            ->and($player->lifetime)->not->toBeNull();
    }
});

it('parses the recorded single player', function () {
    MockClient::global([GetPlayerRequest::class => liveResponse('player')]);

    $player = drawhouse()->player(1);

    expect($player->id)->not->toBeNull()
        ->and($player->accountStatus)->not->toBeNull()
        ->and($player->lifetime)->not->toBeNull();
});

it('parses the recorded games page', function () {
    MockClient::global([GetGamesRequest::class => liveResponse('games')]);

    $games = drawhouse()->games(affiliateId: 1);

    expect($games)->each->toBeInstanceOf(Game::class);

    foreach ($games as $game) {
        expect($game->id)->not->toBeNull()
            ->and($game->state)->not->toBeNull('game state did not parse: '.json_encode($game->raw['state'] ?? null));

        foreach ($game->rounds as $round) {
            expect($round->state)->not->toBeNull('round state did not parse: '.json_encode($round->raw['state'] ?? null));

            foreach ($round->prizes as $prize) {
                expect($prize->type)->not->toBeNull('prize type did not parse: '.json_encode($prize->raw['type'] ?? null));
            }
        }
    }
});

it('parses the recorded live draws', function () {
    MockClient::global([GetLiveDrawsRequest::class => liveResponse('live-draws')]);

    $draws = drawhouse()->liveDraws(affiliateId: 1);

    expect($draws)->not->toBeEmpty()->each->toBeInstanceOf(LiveDraw::class);

    foreach ($draws as $draw) {
        expect($draw->gameId)->not->toBeNull()
            ->and($draw->roundState)->not->toBeNull('roundState did not parse: '.json_encode($draw->raw['roundState'] ?? null))
            ->and($draw->tickets->sold)->not->toBeNull()
            ->and($draw->tickets->available)->not->toBeNull()
            ->and($draw->countdownTarget())->not->toBeNull()
            ->and($draw->asOf)->not->toBeNull();

        foreach ($draw->prizes as $prize) {
            expect($prize->type)->not->toBeNull('prize type did not parse: '.json_encode($prize->raw['type'] ?? null));
        }
    }
});

it('parses the recorded single live draw', function () {
    MockClient::global([GetLiveDrawRequest::class => liveResponse('live-draw')]);

    $draw = drawhouse()->liveDraw(1, affiliateId: 1);

    expect($draw->gameId)->not->toBeNull()
        ->and($draw->edition->url)->not->toBeNull()
        ->and($draw->countdownTarget())->not->toBeNull();
});

it('parses the recorded tickets', function () {
    MockClient::global([GetTicketsRequest::class => liveResponse('tickets')]);

    $tickets = drawhouse()->tickets(1);

    expect($tickets)->toBeArray()->each->toBeInstanceOf(Ticket::class);

    foreach ($tickets as $ticket) {
        expect($ticket->id)->not->toBeNull()
            ->and($ticket->playerId)->not->toBeNull()
            ->and($ticket->price)->not->toBeNull()
            ->and($ticket->createdAt)->not->toBeNull();
    }
});
