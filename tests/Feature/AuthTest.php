<?php

declare(strict_types=1);

use Digitonic\DrawHouse\Auth\TokenStore;
use Digitonic\DrawHouse\DrawHouse;
use Digitonic\DrawHouse\DTO\Partner;
use Digitonic\DrawHouse\DTO\TokenPair;
use Digitonic\DrawHouse\Exceptions\AuthenticationException;
use Digitonic\DrawHouse\Http\Requests\GetMeRequest;
use Digitonic\DrawHouse\Http\Requests\LoginRequest;
use Digitonic\DrawHouse\Http\Requests\RefreshAccessTokenRequest;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\PendingRequest;

it('logs in with the configured credentials and stores both tokens from the bare login body', function () {
    $mock = MockClient::global([
        LoginRequest::class => loginResponse('first'),
    ]);

    $pair = drawhouse()->login();

    expect($pair)->toBeInstanceOf(TokenPair::class)
        ->and($pair->accessToken)->toBe('access-first')
        ->and($pair->refreshToken)->toBe('refresh-first')
        ->and($pair->accessTokenExpiry)->not->toBeNull()
        ->and($pair->refreshTokenExpiry)->not->toBeNull();

    $store = app(TokenStore::class);
    expect($store->accessToken())->toBe('access-first')
        ->and($store->refreshToken())->toBe('refresh-first');

    $login = sentPendingRequest($mock, 0);
    expect($login->getMethod()->value)->toBe('POST')
        ->and($login->getUrl())->toBe('https://api.test.raffletown.co.uk/login')
        ->and($login->body()?->all())->toBe(['email' => 'partner@example.com', 'password' => 'super-secret-password'])
        ->and($login->headers()->get('access-token'))->toBeNull();
});

it('parses the nanosecond-precision expiry timestamps DrawHouse returns', function () {
    $pair = TokenPair::fromLogin([
        'accessToken' => 'a',
        'accessTokenExpiry' => '2026-10-01T12:00:00.000000000Z',
        'refreshToken' => 'r',
        'refreshTokenExpiry' => '2026-11-01T12:00:00.000000000Z',
    ]);

    expect($pair->accessTokenExpiry?->toIso8601ZuluString())->toBe('2026-10-01T12:00:00Z')
        ->and($pair->refreshTokenExpiry?->toIso8601ZuluString())->toBe('2026-11-01T12:00:00Z');
});

it('logs in automatically when no token is cached and sends the access-token header', function () {
    $mock = MockClient::global([
        LoginRequest::class => loginResponse('auto'),
        GetMeRequest::class => meResponse(20),
    ]);

    $me = drawhouse()->me();

    expect($me)->toBeInstanceOf(Partner::class)
        ->and($me->affiliateId)->toBe(20)
        ->and(sentRequests($mock))->toBe([LoginRequest::class, GetMeRequest::class])
        ->and(sentPendingRequest($mock, 1)->headers()->get('access-token'))->toBe('access-auto');
});

it('reuses a cached access token without logging in', function () {
    seedTokens('cached');

    $mock = MockClient::global([
        GetMeRequest::class => meResponse(),
    ]);

    drawhouse()->me();

    expect(sentRequests($mock))->toBe([GetMeRequest::class])
        ->and(sentPendingRequest($mock, 0)->headers()->get('access-token'))->toBe('access-cached');
});

it('refreshes the token on a 401 and retries the request once, keeping the refresh token', function () {
    seedTokens('old');

    $meCalls = 0;

    $mock = MockClient::global([
        GetMeRequest::class => function (PendingRequest $request) use (&$meCalls) {
            $meCalls++;

            return $meCalls === 1 ? unauthorised() : meResponse();
        },
        RefreshAccessTokenRequest::class => refreshResponse('new'),
    ]);

    $me = drawhouse()->me();

    expect($me->affiliateId)->toBe(20)
        ->and(sentRequests($mock))->toBe([GetMeRequest::class, RefreshAccessTokenRequest::class, GetMeRequest::class]);

    $refresh = sentPendingRequest($mock, 1);
    expect($refresh->getUrl())->toBe('https://api.test.raffletown.co.uk/refresh-access-token')
        ->and($refresh->body()?->all())->toBe(['accessToken' => 'access-old', 'refreshToken' => 'refresh-old'])
        ->and($refresh->headers()->get('access-token'))->toBeNull();

    expect(sentPendingRequest($mock, 0)->headers()->get('access-token'))->toBe('access-old')
        ->and(sentPendingRequest($mock, 2)->headers()->get('access-token'))->toBe('access-new')
        ->and(app(TokenStore::class)->accessToken())->toBe('access-new')
        // A refresh leaves the refresh token unchanged.
        ->and(app(TokenStore::class)->refreshToken())->toBe('refresh-old')
        // ...and the next refresh must send the NEW access token in its body.
        ->and(app(TokenStore::class)->accessTokenForRefresh())->toBe('access-new');
});

it('falls back to a fresh login when the refresh is rejected', function () {
    seedTokens('stale');

    $meCalls = 0;

    $mock = MockClient::global([
        GetMeRequest::class => function () use (&$meCalls) {
            $meCalls++;

            return $meCalls === 1 ? unauthorised() : meResponse();
        },
        RefreshAccessTokenRequest::class => envelopeError(401, 'Refresh token expired', 401),
        LoginRequest::class => loginResponse('fresh'),
    ]);

    drawhouse()->me();

    expect(sentRequests($mock))->toBe([
        GetMeRequest::class,
        RefreshAccessTokenRequest::class,
        LoginRequest::class,
        GetMeRequest::class,
    ])
        ->and(sentPendingRequest($mock, 3)->headers()->get('access-token'))->toBe('access-fresh')
        ->and(app(TokenStore::class)->accessToken())->toBe('access-fresh')
        ->and(app(TokenStore::class)->refreshToken())->toBe('refresh-fresh');
});

it('refreshes proactively when the access token has expired but the refresh token has not', function () {
    seedTokens('expiring', accessTtlSeconds: 100);

    $this->travel(101)->seconds();

    $mock = MockClient::global([
        RefreshAccessTokenRequest::class => refreshResponse('renewed'),
        GetMeRequest::class => meResponse(),
    ]);

    drawhouse()->me();

    expect(sentRequests($mock))->toBe([RefreshAccessTokenRequest::class, GetMeRequest::class])
        ->and(sentPendingRequest($mock, 1)->headers()->get('access-token'))->toBe('access-renewed');

    // The expired access token must still be sent in the refresh body, otherwise
    // DrawHouse rejects the refresh and we are forced into a needless login.
    expect(sentPendingRequest($mock, 0)->body()?->all())
        ->toBe(['accessToken' => 'access-expiring', 'refreshToken' => 'refresh-expiring']);
});

it('can refresh directly after the access token has lapsed from the cache', function () {
    seedTokens('lapsed', accessTtlSeconds: 100);
    $this->travel(101)->seconds();
    expect(app(TokenStore::class)->accessToken())->toBeNull();

    $mock = MockClient::global([
        RefreshAccessTokenRequest::class => refreshResponse('renewed'),
    ]);

    $pair = drawhouse()->refreshAccessToken();

    expect($pair->accessToken)->toBe('access-renewed')
        ->and($pair->refreshToken)->toBe('refresh-lapsed')
        ->and(sentPendingRequest($mock, 0)->body()?->all())
        ->toBe(['accessToken' => 'access-lapsed', 'refreshToken' => 'refresh-lapsed'])
        ->and(sentRequests($mock))->toBe([RefreshAccessTokenRequest::class]);
});

it('throws when the refresh response has no newAccessToken', function () {
    seedTokens('old');

    MockClient::global([
        RefreshAccessTokenRequest::class => dataOnly(['somethingElse' => true]),
    ]);

    drawhouse()->refreshAccessToken();
})->throws(AuthenticationException::class, 'newAccessToken');

it('forgetTokens also drops the access token kept for refreshing', function () {
    seedTokens('old');
    drawhouse()->forgetTokens();

    expect(app(TokenStore::class)->accessTokenForRefresh())->toBeNull()
        ->and(app(TokenStore::class)->refreshToken())->toBeNull()
        ->and(app(TokenStore::class)->refreshTokenExpiry())->toBeNull();
});

it('throws an AuthenticationException when still unauthorised after re-authenticating', function () {
    seedTokens('old');

    MockClient::global([
        GetMeRequest::class => unauthorised(),
        RefreshAccessTokenRequest::class => refreshResponse('new'),
    ]);

    drawhouse()->me();
})->throws(AuthenticationException::class, 'unauthorised after re-authenticating');

it('throws an AuthenticationException when the credentials are rejected', function () {
    MockClient::global([
        LoginRequest::class => envelopeError(2, 'Invalid credentials', 401),
    ]);

    try {
        drawhouse()->me();
        $this->fail('Expected AuthenticationException');
    } catch (AuthenticationException $e) {
        expect($e->errorCode)->toBe(2)
            ->and($e->status)->toBe(401)
            ->and($e->getMessage())->toContain('login')
            ->and($e->getMessage())->not->toContain('super-secret-password')
            ->and($e->getMessage())->not->toContain('access-');
    }
});

it('throws when credentials are not configured', function () {
    config()->set('drawhouse.email', null);
    config()->set('drawhouse.password', null);
    app()->forgetInstance(DrawHouse::class);

    MockClient::global([]);

    drawhouse()->me();
})->throws(AuthenticationException::class, 'not configured');

it('honours the token expiry from the response with a safety buffer', function () {
    $this->freezeSecond();
    config()->set('drawhouse.cache.expiry_buffer', 30);
    app()->forgetInstance(TokenStore::class);

    $store = app(TokenStore::class);
    $store->put(TokenPair::fromLogin(loginFixture('ttl', accessTtlSeconds: 100)));

    expect($store->accessToken())->toBe('access-ttl');

    $this->travel(69)->seconds();
    expect($store->accessToken())->toBe('access-ttl');

    $this->travel(2)->seconds();
    expect($store->accessToken())->toBeNull()
        ->and($store->refreshToken())->toBe('refresh-ttl');
});

it('reports when the cached access token lapses, buffer included', function () {
    $this->freezeSecond();
    config()->set('drawhouse.cache.expiry_buffer', 30);
    app()->forgetInstance(TokenStore::class);

    $store = app(TokenStore::class);
    expect($store->accessTokenExpiry())->toBeNull();

    $store->put(TokenPair::fromLogin(loginFixture('ttl', accessTtlSeconds: 100)));
    expect($store->accessTokenExpiry()?->toRfc3339String())->toBe(now('UTC')->addSeconds(70)->toRfc3339String());

    $store->putAccessToken('access-renewed', now('UTC')->addSeconds(600)->toImmutable());
    expect($store->accessTokenExpiry()?->toRfc3339String())->toBe(now('UTC')->addSeconds(570)->toRfc3339String());

    $this->travel(571)->seconds();
    expect($store->accessTokenExpiry())->toBeNull();
});

it('forgets the access token expiry with the tokens', function () {
    seedTokens('old');
    drawhouse()->forgetTokens();

    expect(app(TokenStore::class)->accessTokenExpiry())->toBeNull();
});

it('can forget tokens so the next call logs in again', function () {
    seedTokens('old');

    $mock = MockClient::global([
        LoginRequest::class => loginResponse('again'),
        GetMeRequest::class => meResponse(),
    ]);

    drawhouse()->forgetTokens();
    drawhouse()->me();

    expect(sentRequests($mock))->toBe([LoginRequest::class, GetMeRequest::class]);
});
