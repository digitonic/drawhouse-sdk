<?php

declare(strict_types=1);

use Digitonic\DrawHouse\DTO\Balances;
use Digitonic\DrawHouse\Exceptions\DrawHouseException;
use Digitonic\DrawHouse\Http\Requests\GetBalancesRequest;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

beforeEach(fn () => seedTokens());

it('accepts the full { errorCode, data } envelope', function () {
    MockClient::global([
        GetBalancesRequest::class => envelope(balancesFixture()),
    ]);

    expect(drawhouse()->balances())->toBeInstanceOf(Balances::class);
});

it('accepts a { data } success body without errorCode', function () {
    MockClient::global([
        GetBalancesRequest::class => dataOnly(balancesFixture()),
    ]);

    expect(drawhouse()->balances()->cashBalance)->toBe(50.5);
});

it('throws a DrawHouseException carrying errorCode and status for a non-zero envelope', function () {
    MockClient::global([
        GetBalancesRequest::class => envelopeError(7, 'PartnerListCharityRaffleGames - handler - affiliateId query param is required', 400),
    ]);

    try {
        drawhouse()->balances();
        $this->fail('Expected DrawHouseException');
    } catch (DrawHouseException $e) {
        expect($e->errorCode)->toBe(7)
            ->and($e->status)->toBe(400)
            ->and($e->getMessage())->toContain('affiliateId query param is required')
            ->and($e->isRetryable())->toBeFalse();
    }
});

it('throws for a failed HTTP response without an envelope', function () {
    MockClient::global([
        GetBalancesRequest::class => MockResponse::make('Bad Gateway', 502),
    ]);

    try {
        drawhouse()->balances();
        $this->fail('Expected DrawHouseException');
    } catch (DrawHouseException $e) {
        expect($e->errorCode)->toBeNull()
            ->and($e->status)->toBe(502)
            ->and($e->getMessage())->toContain('502');
    }
});

it('throws for a failed HTTP response whose JSON body has no errorCode', function () {
    MockClient::global([
        GetBalancesRequest::class => MockResponse::make(['message' => 'nope'], 503),
    ]);

    drawhouse()->balances();
})->throws(DrawHouseException::class, '503');

it('rejects a success body that has no data key instead of returning an empty result', function () {
    MockClient::global([
        GetBalancesRequest::class => MockResponse::make(['errorCode' => 0], 200),
    ]);

    try {
        drawhouse()->balances();
        $this->fail('Expected DrawHouseException');
    } catch (DrawHouseException $e) {
        expect($e->getMessage())->toContain('without a data key')
            ->and($e->status)->toBe(200);
    }
});

it('rejects a 200 whose body is not JSON at all', function () {
    MockClient::global([
        GetBalancesRequest::class => MockResponse::make('<html>maintenance</html>', 200),
    ]);

    drawhouse()->balances();
})->throws(DrawHouseException::class, 'envelope');

it('flags HTTP 500 and errorCode 3 as retryable', function () {
    expect((new DrawHouseException('x', 3, 200))->isRetryable())->toBeTrue()
        ->and((new DrawHouseException('x', 1, 500))->isRetryable())->toBeTrue()
        ->and((new DrawHouseException('x', 7, 400))->isRetryable())->toBeFalse();
});
