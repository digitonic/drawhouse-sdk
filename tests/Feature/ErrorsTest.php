<?php

declare(strict_types=1);

use Digitonic\DrawHouse\Auth\TokenStore;
use Digitonic\DrawHouse\Exceptions\AuthenticationException;
use Digitonic\DrawHouse\Exceptions\DrawHouseException;
use Digitonic\DrawHouse\Exceptions\InvalidRequestException;
use Digitonic\DrawHouse\Exceptions\NotFoundException;
use Digitonic\DrawHouse\Exceptions\PartnerNotLiveException;
use Digitonic\DrawHouse\Exceptions\ServerException;
use Digitonic\DrawHouse\Http\Requests\GetBalancesRequest;
use Digitonic\DrawHouse\Http\Requests\GetGamesRequest;
use Digitonic\DrawHouse\Http\Requests\GetMeRequest;
use Digitonic\DrawHouse\Http\Requests\GetTicketsRequest;
use Digitonic\DrawHouse\Http\Requests\LoginRequest;
use Digitonic\DrawHouse\Http\Requests\RefreshAccessTokenRequest;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

// ---------------------------------------------------------------------------
// Error table
// ---------------------------------------------------------------------------

it('maps HTTP 400 / errorCode 7 to InvalidRequestException naming the parameter', function () {
    seedTokens();

    MockClient::global([
        GetGamesRequest::class => envelopeError(7, 'PartnerListCharityRaffleGames - handler - affiliateId query param is required', 400),
    ]);

    try {
        drawhouse()->games(affiliateId: 33);
        $this->fail('Expected InvalidRequestException');
    } catch (InvalidRequestException $e) {
        expect($e->errorCode)->toBe(7)
            ->and($e->status)->toBe(400)
            ->and($e->getMessage())->toContain('affiliateId')
            ->and($e->isRetryable())->toBeFalse();
    }
});

it('maps the 403 partner_not_live object to PartnerNotLiveException carrying the status', function () {
    seedTokens();

    MockClient::global([
        GetBalancesRequest::class => partnerNotLiveResponse('ONBOARDING'),
    ]);

    try {
        drawhouse()->balances();
        $this->fail('Expected PartnerNotLiveException');
    } catch (PartnerNotLiveException $e) {
        expect($e->partnerStatus)->toBe('ONBOARDING')
            ->and($e->status)->toBe(403)
            ->and($e->errorCode)->toBe(403)
            ->and($e->getMessage())->toContain('ONBOARDING')
            ->and($e->isRetryable())->toBeFalse();
    }
});

it('keeps a plain 403 (not a partner) as a generic DrawHouseException', function () {
    seedTokens();

    MockClient::global([
        GetBalancesRequest::class => envelopeError(403, 'Forbidden', 403),
    ]);

    try {
        drawhouse()->balances();
        $this->fail('Expected DrawHouseException');
    } catch (DrawHouseException $e) {
        expect($e)->not->toBeInstanceOf(PartnerNotLiveException::class)
            ->and($e->status)->toBe(403);
    }
});

it('maps 404 to NotFoundException, including /v2/partners/me with no profile', function () {
    seedTokens();

    MockClient::global([
        GetMeRequest::class => envelopeError(404, 'Not found', 404),
    ]);

    drawhouse()->me();
})->throws(NotFoundException::class);

it('maps 500 / errorCode 3 to a retryable ServerException once the automatic retry is exhausted', function () {
    seedTokens();

    $mock = MockClient::global([
        GetGamesRequest::class => envelopeError(3, 'Backend failure', 500),
    ]);

    try {
        drawhouse()->games(affiliateId: 33);
        $this->fail('Expected ServerException');
    } catch (ServerException $e) {
        expect($e->isRetryable())->toBeTrue()
            ->and($e->errorCode)->toBe(3)
            ->and($e->status)->toBe(500)
            ->and(sentRequests($mock))->toHaveCount(2);
    }
});

it('maps a non-envelope 404 and 500 the same way', function () {
    expect(DrawHouseException::http(404))->toBeInstanceOf(NotFoundException::class)
        ->and(DrawHouseException::http(500))->toBeInstanceOf(ServerException::class)
        ->and(DrawHouseException::http(400))->toBeInstanceOf(InvalidRequestException::class)
        ->and(DrawHouseException::http(502))->toBeInstanceOf(ServerException::class)
        ->and(DrawHouseException::http(502)->isRetryable())->toBeFalse();
});

// ---------------------------------------------------------------------------
// Auth
// ---------------------------------------------------------------------------

it('accepts errorCode 200 as success on the refresh route only', function () {
    seedTokens('old');

    MockClient::global([
        RefreshAccessTokenRequest::class => refreshResponse('new', errorCode: 200),
    ]);

    expect(drawhouse()->refreshAccessToken()->accessToken)->toBe('access-new')
        ->and(app(TokenStore::class)->accessToken())->toBe('access-new');
});

it('still accepts errorCode 0 on the refresh route', function () {
    seedTokens('old');

    MockClient::global([
        RefreshAccessTokenRequest::class => refreshResponse('zero', errorCode: 0),
    ]);

    expect(drawhouse()->refreshAccessToken()->accessToken)->toBe('access-zero');
});

it('does not treat errorCode 200 as success on any other route', function () {
    seedTokens();

    MockClient::global([
        GetBalancesRequest::class => envelope(balancesFixture(), 200, 200),
    ]);

    drawhouse()->balances();
})->throws(DrawHouseException::class, 'error 200');

it('logs in afresh when the refresh answers 422 (refresh token expired or mismatched)', function () {
    seedTokens('stale');

    $meCalls = 0;

    $mock = MockClient::global([
        GetMeRequest::class => function () use (&$meCalls) {
            $meCalls++;

            return $meCalls === 1 ? unauthorised() : meResponse();
        },
        RefreshAccessTokenRequest::class => envelopeError(422, 'Refresh token expired', 422),
        LoginRequest::class => loginResponse('fresh'),
    ]);

    drawhouse()->me();

    expect(sentRequests($mock))->toBe([
        GetMeRequest::class,
        RefreshAccessTokenRequest::class,
        LoginRequest::class,
        GetMeRequest::class,
    ])
        ->and(app(TokenStore::class)->accessToken())->toBe('access-fresh')
        ->and(app(TokenStore::class)->refreshToken())->toBe('refresh-fresh');
});

it('surfaces a 422 refresh as an AuthenticationException when called directly', function () {
    seedTokens('stale');

    MockClient::global([
        RefreshAccessTokenRequest::class => envelopeError(422, 'Refresh token expired', 422),
    ]);

    try {
        drawhouse()->refreshAccessToken();
        $this->fail('Expected AuthenticationException');
    } catch (AuthenticationException $e) {
        expect($e->isRefreshTokenExpired())->toBeTrue()
            ->and($e->status)->toBe(422)
            ->and($e->getMessage())->toContain('expired or does not match');
    }
});

it('surfaces a 400 refresh (missing token) as an AuthenticationException', function () {
    seedTokens('stale');

    MockClient::global([
        RefreshAccessTokenRequest::class => envelopeError(7, 'refreshToken is required', 400),
    ]);

    try {
        drawhouse()->refreshAccessToken();
        $this->fail('Expected AuthenticationException');
    } catch (AuthenticationException $e) {
        expect($e->status)->toBe(400)
            ->and($e->isRefreshTokenExpired())->toBeFalse()
            ->and($e->getMessage())->toContain('missing');
    }
});

it('distinguishes the three documented login failures', function (int $status, string $reason, bool $blocked) {
    MockClient::global([
        LoginRequest::class => MockResponse::make(['errorCode' => $status, 'data' => 'nope'], $status),
    ]);

    try {
        drawhouse()->login();
        $this->fail('Expected AuthenticationException');
    } catch (AuthenticationException $e) {
        expect($e->status)->toBe($status)
            ->and($e->getMessage())->toContain($reason)
            ->and($e->isAccountBlocked())->toBe($blocked)
            ->and($e->getMessage())->not->toContain('super-secret-password');
    }
})->with([
    'malformed body' => [400, 'malformed', false],
    'wrong credentials' => [401, 'wrong credentials', false],
    'blocked' => [403, 'blocked', true],
]);

it('ignores playerDetails on the login response', function () {
    MockClient::global([
        LoginRequest::class => MockResponse::make(loginFixture('pd') + ['playerDetails' => ['id' => 25, 'email' => 'partner@example.com']], 200),
    ]);

    expect(drawhouse()->login()->accessToken)->toBe('access-pd');
});

// ---------------------------------------------------------------------------
// Partner profile
// ---------------------------------------------------------------------------

it('parses the full v1.2 partner profile', function () {
    seedTokens();

    MockClient::global([
        GetMeRequest::class => envelope(partnerFixture(20, [
            'pooledRevShare' => 0.25,
            'ringFencedRevShare' => 0.4,
            'minimumGuaranteedIncome' => 1000,
            'launchProposalDate' => '2026-11-01',
            'launchProposalNotes' => 'TBC',
        ])),
    ]);

    $me = drawhouse()->me();

    expect($me->affiliateId)->toBe(20)
        ->and($me->isLive())->toBeTrue()
        ->and($me->status)->toBe('LIVE')
        ->and($me->playerId)->toBe(25)
        ->and($me->email)->toBe('partner@example.com')
        ->and($me->firstName)->toBe('Jo')
        ->and($me->lastName)->toBe('Bloggs')
        ->and($me->phone)->toBe('+447700900000')
        ->and($me->companyName)->toBe('Example Media')
        ->and($me->crmInScope)->toBeTrue()
        ->and($me->pooledRevShare)->toBe(0.25)
        ->and($me->ringFencedRevShare)->toBe(0.4)
        ->and($me->minimumGuaranteedIncome)->toBe(1000.0)
        ->and($me->launchProposal)->toBe(['launchProposalDate' => '2026-11-01', 'launchProposalNotes' => 'TBC'])
        ->and($me->raw['companyName'])->toBe('Example Media');
});

it('tolerates a nullable affiliateId on the profile and reports the partner as not live', function () {
    seedTokens();

    MockClient::global([
        GetMeRequest::class => meResponse(null),
    ]);

    $me = drawhouse()->me();

    expect($me->affiliateId)->toBeNull()
        ->and($me->status)->toBe('ONBOARDING')
        ->and($me->isLive())->toBeFalse();
});

it('throws PartnerNotLiveException from affiliateId() while affiliateId is null, and does not cache it', function () {
    seedTokens();

    $mock = MockClient::global([
        GetMeRequest::class => meResponse(null),
    ]);

    try {
        drawhouse()->affiliateId();
        $this->fail('Expected PartnerNotLiveException');
    } catch (PartnerNotLiveException $e) {
        expect($e->partnerStatus)->toBe('ONBOARDING')
            ->and(app(TokenStore::class)->affiliateId())->toBeNull();
    }

    // A later call asks again rather than remembering the failure.
    try {
        drawhouse()->affiliateId();
    } catch (PartnerNotLiveException) {
    }

    expect(sentRequests($mock))->toBe([GetMeRequest::class, GetMeRequest::class]);
});

// ---------------------------------------------------------------------------
// Draw tickets
// ---------------------------------------------------------------------------

it('maps a 404 from the tickets route to NotFoundException like any other route', function () {
    seedTokens();

    MockClient::global([
        GetTicketsRequest::class => envelopeError(404, 'Game not found', 404),
    ]);

    drawhouse()->tickets(101);
})->throws(NotFoundException::class);

it('passes other tickets failures through untouched', function () {
    seedTokens();

    MockClient::global([
        GetTicketsRequest::class => envelopeError(7, 'gameId is required', 400),
    ]);

    drawhouse()->tickets(101);
})->throws(InvalidRequestException::class);
