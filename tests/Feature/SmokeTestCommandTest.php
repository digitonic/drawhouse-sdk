<?php

declare(strict_types=1);

use Digitonic\DrawHouse\Http\Requests\GetLiveDrawsRequest;
use Digitonic\DrawHouse\Http\Requests\GetMeRequest;
use Digitonic\DrawHouse\Http\Requests\GetPlayersRequest;
use Digitonic\DrawHouse\Http\Requests\LoginRequest;
use Saloon\Http\Faking\MockClient;

it('runs the opt-in smoke command against faked endpoints without printing tokens or PII', function () {
    MockClient::global([
        LoginRequest::class => loginResponse('smoke'),
        GetMeRequest::class => meResponse(20),
        GetPlayersRequest::class => playersPageResponse([playerDetailFixture(123)], null),
        GetLiveDrawsRequest::class => liveDrawsResponse([liveDrawFixture(40)]),
    ]);

    $this->artisan('drawhouse:smoke', ['--players' => true, '--live' => true])
        ->expectsOutputToContain('[ok] login')
        ->expectsOutputToContain('status=LIVE affiliateId=20')
        ->expectsOutputToContain('items=1 nextCursor=null firstId=123 status=ACTIVE channel=PAID')
        ->expectsOutputToContain('game=40 round=88 state=OPEN sold=3120 available=6880 countdown=2026-09-30T20:00:00Z')
        ->doesntExpectOutputToContain('access-smoke')
        ->doesntExpectOutputToContain('jo@example.com')
        ->assertSuccessful();
});

it('reports a partner that is not LIVE and carries on', function () {
    MockClient::global([
        LoginRequest::class => loginResponse('smoke'),
        GetMeRequest::class => meResponse(null),
        GetPlayersRequest::class => partnerNotLiveResponse('ONBOARDING'),
    ]);

    $this->artisan('drawhouse:smoke', ['--players' => true])
        ->expectsOutputToContain('Partner is not LIVE')
        ->expectsOutputToContain('[403 partner_not_live] GET /v2/partners/players?limit=1: partner status is ONBOARDING')
        ->assertSuccessful();
});

it('fails when login is rejected', function () {
    MockClient::global([
        LoginRequest::class => envelopeError(401, 'Unauthorized', 401),
    ]);

    $this->artisan('drawhouse:smoke')
        ->expectsOutputToContain('wrong credentials')
        ->assertFailed();
});
