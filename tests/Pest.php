<?php

declare(strict_types=1);

use Digitonic\DrawHouse\Auth\TokenStore;
use Digitonic\DrawHouse\DrawHouse;
use Digitonic\DrawHouse\DTO\TokenPair;
use Digitonic\DrawHouse\Tests\TestCase;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\PendingRequest;

pest()->extend(TestCase::class)->in('Feature');

// ---------------------------------------------------------------------------
// Fixtures — shaped like DrawHouse Partner API v1.2 responses. All values are
// fake.
// ---------------------------------------------------------------------------

/**
 * `{ errorCode, data }` — the documented standard envelope.
 */
function envelope(mixed $data, int $status = 200, int $errorCode = 0): MockResponse
{
    return MockResponse::make(['errorCode' => $errorCode, 'data' => $data], $status);
}

/**
 * `{ data }` — a success response without `errorCode`.
 */
function dataOnly(mixed $data, int $status = 200): MockResponse
{
    return MockResponse::make(['data' => $data], $status);
}

function envelopeError(int $errorCode, string $message, int $status = 200): MockResponse
{
    return envelope($message, $status, $errorCode);
}

function unauthorised(): MockResponse
{
    return envelopeError(401, 'Unauthorized', 401);
}

/**
 * POST /login response — NOT enveloped; tokens at the top level.
 *
 * @return array<string, string>
 */
function loginFixture(string $suffix, ?int $accessTtlSeconds = null, ?int $refreshTtlSeconds = null): array
{
    return [
        'accessToken' => "access-{$suffix}",
        'accessTokenExpiry' => now('UTC')->addSeconds($accessTtlSeconds ?? 3600)->format('Y-m-d\TH:i:s.000000000\Z'),
        'refreshToken' => "refresh-{$suffix}",
        'refreshTokenExpiry' => now('UTC')->addSeconds($refreshTtlSeconds ?? 30 * 86400)->format('Y-m-d\TH:i:s.000000000\Z'),
    ];
}

function loginResponse(string $suffix, ?int $accessTtlSeconds = null): MockResponse
{
    return MockResponse::make(loginFixture($suffix, $accessTtlSeconds), 200);
}

/**
 * POST /refresh-access-token response — `{ errorCode: 200, data: { newAccessToken, newAccessTokenExpiry } }`.
 * This route answers errorCode 200 on success, not 0.
 */
function refreshResponse(string $suffix, ?int $accessTtlSeconds = null, int $errorCode = 200): MockResponse
{
    return envelope([
        'newAccessToken' => "access-{$suffix}",
        'newAccessTokenExpiry' => now('UTC')->addSeconds($accessTtlSeconds ?? 3600)->format('Y-m-d\TH:i:s.000000000\Z'),
    ], 200, $errorCode);
}

/**
 * GET /v2/partners/me — the v1.2 partner profile.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function partnerFixture(?int $affiliateId = 20, array $overrides = []): array
{
    return array_replace([
        'playerId' => 25,
        'email' => 'partner@example.com',
        'firstName' => 'Jo',
        'lastName' => 'Bloggs',
        'phone' => '+447700900000',
        'companyName' => 'Example Media',
        'status' => $affiliateId === null ? 'ONBOARDING' : 'LIVE',
        'affiliateId' => $affiliateId,
        'crmInScope' => true,
    ], $overrides);
}

function meResponse(?int $affiliateId = 20): MockResponse
{
    return envelope(partnerFixture($affiliateId));
}

/**
 * The 403 whose `data` is an object rather than a message string.
 */
function partnerNotLiveResponse(string $status = 'ONBOARDING'): MockResponse
{
    return envelope(['error' => 'partner_not_live', 'status' => $status], 403, 403);
}

/**
 * One row of GET /v2/partners/players.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function playerDetailFixture(int $id = 123, array $overrides = []): array
{
    return array_replace_recursive([
        'id' => $id,
        'email' => 'jo@example.com',
        'firstName' => 'Jo',
        'lastName' => 'Bloggs',
        'displayName' => 'jo_b',
        'phone' => '+447700900000',
        'dateOfBirth' => '1990-01-31',
        'gender' => 'F',
        'registeredAt' => '2026-09-01T09:12:00Z',
        'updatedAt' => '2026-09-27T18:04:11Z',
        'accountStatus' => 'ACTIVE',
        'statusChangedAt' => '2026-09-01T09:12:00Z',
        'marketable' => true,
        'suppression' => [
            'selfExcluded' => false,
            'selfExclusionUntil' => null,
            'selfExclusionForever' => false,
            'gamstop' => false,
            'timedOutUntil' => null,
            'kycStatus' => 'VERIFIED',
            'emailSuppressed' => false,
        ],
        'attribution' => [
            'utmSource' => 'acme',
            'utmMedium' => 'cpc',
            'utmCampaign' => 'sept-draw',
            'acquisitionChannel' => 'PAID',
            'landingUrl' => 'https://acme.example/prize-draw/sept?utm_source=acme&clickid=abc123',
            'params' => ['utm_source' => 'acme', 'utm_medium' => 'cpc', 'utm_campaign' => 'sept-draw', 'clickid' => 'abc123'],
        ],
        'activity' => [
            'lastLoginAt' => '2026-09-27T18:04:11Z',
            'firstPurchaseAt' => '2026-09-02T20:15:00Z',
            'lastPurchaseAt' => '2026-09-25T21:40:00Z',
            'firstDepositAt' => '2026-09-02T20:14:30Z',
        ],
        'consents' => [
            'email' => ['granted' => true, 'recordedAt' => '2026-09-01T09:12:00Z'],
            'sms' => ['granted' => false, 'recordedAt' => '2026-09-01T09:12:00Z'],
            'partnerOffers' => ['granted' => true, 'recordedAt' => null],
        ],
        'lifetime' => [
            'depositCount' => 3,
            'depositValue' => 60.0,
            'cashSpend' => 55.0,
            'siteCreditSpend' => 5.0,
            'refunds' => 0,
            'cashPrizesWon' => 0,
            'withdrawals' => 0,
        ],
    ], $overrides);
}

/**
 * GET /v2/partners/players — `{ errorCode: 0, data: { items: [...], nextCursor } }`.
 *
 * @param  list<array<string, mixed>>  $items
 */
function playersPageResponse(array $items, ?string $nextCursor): MockResponse
{
    return envelope(['items' => $items, 'nextCursor' => $nextCursor]);
}

/**
 * The legacy GET /v2/partners/reports/players row.
 *
 * @return array<string, mixed>
 */
function playerFixture(): array
{
    return [
        'id' => 123,
        'email' => 'player@gmail.com',
        'firstName' => '',
        'lastName' => '',
        'dateOfBirth' => '1999-01-01',
        'phone' => '07123456789',
        'registrationDate' => '20260801',
    ];
}

/**
 * A CUMULATIVE snapshot as at the end of the report day.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function dailyReportFixture(array $overrides = []): array
{
    return array_replace([
        'id' => 1234,
        'registrations' => 210,
        'played' => 151,
        'active' => 160,
        'internalMarketable' => 120,
        'externalMarketable' => 95,
        'uniqueVisitors' => 2400,
        'pageViews' => 6100,
        'startAt' => '2026-08-01T00:00:00Z',
    ], $overrides);
}

/**
 * @return list<array<string, mixed>>
 */
function utmFixture(): array
{
    return [
        ['source' => 'default', 'medium' => 'default', 'campaign' => 'default', 'count' => 139, 'registered' => 80],
        ['source' => 'newsletter', 'medium' => 'email', 'campaign' => 'autumn-prize-draw', 'count' => 241, 'registered' => 130],
    ];
}

/**
 * A Game with every documented field.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function gameFixture(int $id = 101, array $overrides = []): array
{
    return array_replace([
        'id' => $id,
        'name' => 'Weekly Charity Raffle',
        'state' => 'INPROGRESS',
        'currentRoundId' => 9001,
        'isParticipating' => true,
        'editions' => [
            ['id' => 501, 'affiliateId' => 20, 'name' => 'Acme Weekly', 'slug' => 'acme-weekly', 'isDefault' => true, 'isMine' => true],
            ['id' => 502, 'affiliateId' => 33, 'name' => 'Other Brand Weekly', 'slug' => 'other-brand-weekly', 'isDefault' => true, 'isMine' => false],
        ],
        'fields' => [
            'raffleEntryType' => 'INSTANT+DRAW',
            'revealMechanic' => 'SCAN',
            'hasCashPrize' => 'true',
            'maxTickets' => '550',
            'maxTicketsPerPlayer' => '550',
            'defaultCheckoutTicketCount' => '10',
            'entriesCount' => '0',
            'scheduledDrawTime' => '2026-09-28T19:00:00.000Z',
            'rafflePrizePercentage' => '50',
            'physicalToCashPercentage' => '80',
            'physicalToSiteCreditPercentage' => '100',
            'cashToSiteCreditPercentage' => '110',
            'isRingFenced' => 'true',
        ],
        'rounds' => [
            [
                'id' => 9001,
                'roundNumber' => 1,
                'state' => 'OPEN',
                'ticketPrice' => '0.20',
                'schedule' => [
                    'opensAt' => '2026-09-21T00:00:00Z',
                    'drawAt' => '2026-09-28T18:45:00Z',
                    'liveDrawAt' => '2026-09-28T19:00:00Z',
                    'completesAt' => '2026-09-28T19:30:00Z',
                ],
                'nextTransition' => [
                    'toState' => 'RESULTED',
                    'scheduledAt' => '2026-09-28T18:45:00Z',
                ],
                'prizes' => [
                    ['name' => '£550 cash', 'type' => 'DRAW', 'amount' => 550, 'category' => 'CASH', 'totalAvailable' => 1, 'soldCount' => 0],
                    ['name' => '£5 site credit', 'type' => 'INSTANT_SITE_CREDIT', 'amount' => 5, 'category' => 'SITE_CREDIT', 'totalAvailable' => 50, 'soldCount' => 12],
                ],
            ],
        ],
    ], $overrides);
}

/**
 * GET /v2/partners/charity-raffles/games — `{ errorCode: 0, data: { games: [...] } }`.
 *
 * @param  list<array<string, mixed>>  $games
 */
function gamesResponse(array $games): MockResponse
{
    return envelope(['games' => $games]);
}

/**
 * One item of GET /v2/partners/charity-raffles/live.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function liveDrawFixture(int $gameId = 40, array $overrides = []): array
{
    return array_replace_recursive([
        'gameId' => $gameId,
        'gameName' => 'September Super Draw',
        'roundId' => 88,
        'roundState' => 'OPEN',
        'entryType' => 'DRAW_ONLY',
        'edition' => [
            'id' => 7,
            'name' => 'Acme September Draw',
            'slug' => 'acme-september-draw',
            'description' => 'Win a car and support a good cause.',
            'url' => 'https://acme.example/prize-draw/acme-september-draw',
        ],
        'images' => [
            'hero' => ['base' => 'https://cdn.example/hero.jpg', 'variants' => ['2x' => 'https://cdn.example/hero@2x.jpg']],
            'lobby-card' => ['base' => 'https://cdn.example/card.jpg'],
        ],
        'tickets' => ['price' => '5.00', 'max' => 10000, 'sold' => 3120, 'available' => 6880, 'maxPerPlayer' => 550],
        'schedule' => [
            'opensAt' => '2026-09-01T09:00:00Z',
            'drawAt' => '2026-09-30T20:00:00Z',
            'completesAt' => '2026-10-01T09:00:00Z',
        ],
        'nextTransition' => ['toState' => 'RESULTED', 'scheduledAt' => '2026-09-30T20:00:00Z'],
        'scheduledDrawTime' => '2026-09-30T20:00:00.000Z',
        'prizes' => [
            ['prizePoolId' => 3, 'name' => 'Electric Hatchback', 'type' => 'DRAW', 'category' => 'PHYSICAL', 'imageUrl' => 'https://cdn.example/car.jpg', 'totalAvailable' => 1, 'displayOrder' => 1],
            ['prizePoolId' => 4, 'name' => '£10 site credit', 'type' => 'INSTANT', 'category' => 'SITE_CREDIT', 'amount' => 10, 'totalAvailable' => 300],
        ],
        'instantWins' => [
            'enabled' => true,
            'totalPrizes' => 300,
            'remaining' => 212,
            'revealed' => 88,
            'claimed' => 80,
            'totalValue' => 3000.0,
            'perPrize' => [['prizePoolId' => 4, 'name' => '£10 site credit', 'amount' => 10, 'total' => 300, 'remaining' => 212, 'revealed' => 88, 'claimed' => 80]],
        ],
        'liveDraw' => null,
        'asOf' => '2026-09-28T12:00:00Z',
    ], $overrides);
}

/**
 * GET /v2/partners/charity-raffles/live — `{ errorCode: 0, data: { draws: [...] } }`.
 *
 * @param  list<array<string, mixed>>  $draws
 */
function liveDrawsResponse(array $draws): MockResponse
{
    return envelope(['draws' => $draws]);
}

/**
 * @return array<string, mixed>
 */
function ticketFixture(): array
{
    return [
        'id' => 123456789,
        'playerId' => 123,
        'price' => 1.00,
        'paymentStatus' => 'SUCCEEDED',
        'createdAt' => '2026-08-01T12:00:00.000000Z',
        'utmSource' => '',
        'utmMedium' => '',
        'utmCampaign' => '',
    ];
}

/**
 * @return array<string, mixed>
 */
function balancesFixture(): array
{
    return [
        'cashBalance' => 50.50,
        'siteCreditBalance' => 12.34,
        'vaultKeyBalance' => 100,
    ];
}

/**
 * Seed the cache with a token pair as though we had already logged in.
 */
function seedTokens(string $suffix = 'old', ?int $accessTtlSeconds = null): TokenPair
{
    $pair = TokenPair::fromLogin(loginFixture($suffix, $accessTtlSeconds));

    app(TokenStore::class)->put($pair);

    return $pair;
}

function drawhouse(): DrawHouse
{
    return app(DrawHouse::class);
}

/**
 * The request class names sent so far, in order.
 *
 * @return list<class-string>
 */
function sentRequests(MockClient $mock): array
{
    return array_map(
        static fn ($response) => $response->getPendingRequest()->getRequest()::class,
        $mock->getRecordedResponses(),
    );
}

function sentPendingRequest(MockClient $mock, int $index): PendingRequest
{
    return $mock->getRecordedResponses()[$index]->getPendingRequest();
}

/**
 * The final query string as it would go on the wire.
 */
function sentQueryString(MockClient $mock, int $index): string
{
    return $mock->getRecordedResponses()[$index]->getPsrRequest()->getUri()->getQuery();
}
