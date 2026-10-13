<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Digitonic\DrawHouse\DTO\Balances;
use Digitonic\DrawHouse\DTO\DailyReport;
use Digitonic\DrawHouse\DTO\Player;
use Digitonic\DrawHouse\DTO\Ticket;
use Digitonic\DrawHouse\DTO\UtmBreakdown;
use Digitonic\DrawHouse\Http\Requests\GetAffiliateReportsRequest;
use Digitonic\DrawHouse\Http\Requests\GetBalancesRequest;
use Digitonic\DrawHouse\Http\Requests\GetPlayersReportRequest;
use Digitonic\DrawHouse\Http\Requests\GetReportUtmsRequest;
use Digitonic\DrawHouse\Http\Requests\GetTicketsRequest;
use Saloon\Http\Faking\MockClient;

beforeEach(fn () => seedTokens());

it('fetches the players report', function () {
    $mock = MockClient::global([
        GetPlayersReportRequest::class => dataOnly([playerFixture()]),
    ]);

    $players = drawhouse()->players();

    expect($players)->toHaveCount(1)
        ->and($players[0])->toBeInstanceOf(Player::class)
        ->and($players[0]->id)->toBe(123)
        ->and($players[0]->email)->toBe('player@gmail.com')
        ->and($players[0]->firstName)->toBe('')
        ->and($players[0]->phone)->toBe('07123456789')
        ->and($players[0]->dateOfBirth?->toDateString())->toBe('1999-01-01')
        ->and($players[0]->registrationDate?->toDateString())->toBe('2026-08-01');

    $request = sentPendingRequest($mock, 0);
    expect($request->getUrl())->toBe('https://api.test.raffletown.co.uk/v2/partners/reports/players')
        ->and($request->headers()->get('access-token'))->toBe('access-old');
});

it('fetches daily affiliate reports for a date range', function () {
    $mock = MockClient::global([
        GetAffiliateReportsRequest::class => dataOnly([dailyReportFixture()]),
    ]);

    $reports = drawhouse()->dailyReports(CarbonImmutable::parse('2026-08-01'), '2026-08-02');

    expect($reports)->toHaveCount(1)
        ->and($reports[0])->toBeInstanceOf(DailyReport::class)
        ->and($reports[0]->id)->toBe(1234)
        ->and($reports[0]->registrations)->toBe(210)
        ->and($reports[0]->played)->toBe(151)
        ->and($reports[0]->startAt?->toIso8601ZuluString())->toBe('2026-08-01T00:00:00Z');

    expect(sentPendingRequest($mock, 0)->getUrl())->toBe('https://api.test.raffletown.co.uk/v2/affiliates/reports')
        ->and(sentQueryString($mock, 0))->toBe('interval=days&start=2026-08-01&end=2026-08-02');
});

it('fetches the UTM breakdown for a daily report', function () {
    $mock = MockClient::global([
        GetReportUtmsRequest::class => dataOnly(utmFixture()),
    ]);

    $utms = drawhouse()->utms(DailyReport::fromArray(dailyReportFixture()));

    expect($utms)->toHaveCount(2)
        ->and($utms[0])->toBeInstanceOf(UtmBreakdown::class)
        ->and($utms[0]->isUntracked())->toBeTrue()
        ->and($utms[0]->count)->toBe(139)
        ->and($utms[0]->registered)->toBe(80)
        ->and($utms[1]->source)->toBe('newsletter')
        ->and($utms[1]->medium)->toBe('email')
        ->and($utms[1]->campaign)->toBe('autumn-prize-draw')
        ->and($utms[1]->isUntracked())->toBeFalse()
        ->and(sentPendingRequest($mock, 0)->getUrl())->toBe('https://api.test.raffletown.co.uk/v2/affiliates/reports/1234/utms');
});

it('accepts a plain daily report id for the UTM breakdown', function () {
    $mock = MockClient::global([
        GetReportUtmsRequest::class => dataOnly([]),
    ]);

    expect(drawhouse()->utms(99))->toBe([])
        ->and(sentPendingRequest($mock, 0)->getUrl())->toEndWith('/v2/affiliates/reports/99/utms');
});

it('fetches tickets for a game', function () {
    $mock = MockClient::global([
        GetTicketsRequest::class => dataOnly(['tickets' => [ticketFixture()]]),
    ]);

    $tickets = drawhouse()->tickets(101);

    expect($tickets)->toHaveCount(1)
        ->and($tickets[0])->toBeInstanceOf(Ticket::class)
        ->and($tickets[0]->id)->toBe(123456789)
        ->and($tickets[0]->playerId)->toBe(123)
        ->and($tickets[0]->price)->toBe(1.0)
        ->and($tickets[0]->paymentStatus)->toBe('SUCCEEDED')
        ->and($tickets[0]->createdAt?->toIso8601ZuluString())->toBe('2026-08-01T12:00:00Z')
        ->and($tickets[0]->utmSource)->toBeNull()
        ->and($tickets[0]->hasUtm())->toBeFalse();

    $request = sentPendingRequest($mock, 0);
    expect($request->getUrl())->toBe('https://api.test.raffletown.co.uk/v2/games/tickets')
        ->and(sentQueryString($mock, 0))->toBe('gameId=101')
        ->and($request->headers()->get('access-token'))->toBe('access-old');
});

it('fetches the single aggregate balances object', function () {
    $mock = MockClient::global([
        GetBalancesRequest::class => dataOnly(balancesFixture()),
    ]);

    $balances = drawhouse()->balances();

    expect($balances)->toBeInstanceOf(Balances::class)
        ->and($balances->cashBalance)->toBe(50.5)
        ->and($balances->siteCreditBalance)->toBe(12.34)
        ->and($balances->vaultKeyBalance)->toBe(100.0)
        ->and(sentPendingRequest($mock, 0)->getUrl())->toBe('https://api.test.raffletown.co.uk/v2/players/balances');
});
