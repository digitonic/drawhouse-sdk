<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Digitonic\DrawHouse\DTO\PlayerDetail;
use Digitonic\DrawHouse\DTO\PlayerPage;
use Digitonic\DrawHouse\Enums\AccountStatus;
use Digitonic\DrawHouse\Enums\AcquisitionChannel;
use Digitonic\DrawHouse\Exceptions\InvalidRequestException;
use Digitonic\DrawHouse\Exceptions\NotFoundException;
use Digitonic\DrawHouse\Exceptions\PartnerNotLiveException;
use Digitonic\DrawHouse\Http\Requests\GetPlayerRequest;
use Digitonic\DrawHouse\Http\Requests\GetPlayersRequest;
use Digitonic\DrawHouse\Support\PlayersQuery;
use Illuminate\Support\LazyCollection;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\PendingRequest;

beforeEach(fn () => seedTokens());

// ---------------------------------------------------------------------------
// DTO
// ---------------------------------------------------------------------------

it('parses every field of a v1.2 players-feed row', function () {
    $p = PlayerDetail::fromArray(playerDetailFixture());

    expect($p->id)->toBe(123)
        ->and($p->email)->toBe('jo@example.com')
        ->and($p->firstName)->toBe('Jo')
        ->and($p->displayName)->toBe('jo_b')
        ->and($p->phone)->toBe('+447700900000')
        ->and($p->dateOfBirth?->toDateString())->toBe('1990-01-31')
        ->and($p->gender)->toBe('F')
        ->and($p->registeredAt?->toIso8601ZuluString())->toBe('2026-09-01T09:12:00Z')
        ->and($p->updatedAt?->toIso8601ZuluString())->toBe('2026-09-27T18:04:11Z')
        ->and($p->accountStatus)->toBe(AccountStatus::ACTIVE)
        ->and($p->statusChangedAt?->toIso8601ZuluString())->toBe('2026-09-01T09:12:00Z')
        ->and($p->marketable)->toBeTrue();

    expect($p->suppression->selfExcluded)->toBeFalse()
        ->and($p->suppression->selfExclusionUntil)->toBeNull()
        ->and($p->suppression->selfExclusionForever)->toBeFalse()
        ->and($p->suppression->gamstop)->toBeFalse()
        ->and($p->suppression->timedOutUntil)->toBeNull()
        ->and($p->suppression->kycStatus)->toBe('VERIFIED')
        ->and($p->suppression->isKycVerified())->toBeTrue()
        ->and($p->suppression->emailSuppressed)->toBeFalse();

    expect($p->attribution->utmSource)->toBe('acme')
        ->and($p->attribution->utmMedium)->toBe('cpc')
        ->and($p->attribution->utmCampaign)->toBe('sept-draw')
        ->and($p->attribution->acquisitionChannel)->toBe(AcquisitionChannel::PAID)
        ->and($p->attribution->landingUrl)->toBe('https://acme.example/prize-draw/sept?utm_source=acme&clickid=abc123')
        ->and($p->attribution->params)->toBe(['utm_source' => 'acme', 'utm_medium' => 'cpc', 'utm_campaign' => 'sept-draw', 'clickid' => 'abc123'])
        ->and($p->attribution->param('CLICKID'))->toBe('abc123')
        ->and($p->attribution->param('gclid'))->toBeNull()
        ->and($p->attribution->hasUtm())->toBeTrue();

    expect($p->activity->lastLoginAt?->toIso8601ZuluString())->toBe('2026-09-27T18:04:11Z')
        ->and($p->activity->firstPurchaseAt?->toIso8601ZuluString())->toBe('2026-09-02T20:15:00Z')
        ->and($p->activity->lastPurchaseAt?->toIso8601ZuluString())->toBe('2026-09-25T21:40:00Z')
        ->and($p->activity->firstDepositAt?->toIso8601ZuluString())->toBe('2026-09-02T20:14:30Z')
        ->and($p->activity->hasPurchased())->toBeTrue()
        ->and($p->activity->hasDeposited())->toBeTrue();

    expect($p->consents->email->isGranted())->toBeTrue()
        ->and($p->consents->email->recordedAt?->toIso8601ZuluString())->toBe('2026-09-01T09:12:00Z')
        ->and($p->consents->sms->isGranted())->toBeFalse()
        ->and($p->consents->partnerOffers->isGranted())->toBeTrue()
        // partnerOffers.recordedAt is always null; that flag has no timestamp.
        ->and($p->consents->partnerOffers->recordedAt)->toBeNull();

    expect($p->lifetime)->not->toBeNull()
        ->and($p->lifetime?->depositCount)->toBe(3)
        ->and($p->lifetime?->depositValue)->toBe(60.0)
        ->and($p->lifetime?->cashSpend)->toBe(55.0)
        ->and($p->lifetime?->siteCreditSpend)->toBe(5.0)
        ->and($p->lifetime?->refunds)->toBe(0.0)
        ->and($p->lifetime?->cashPrizesWon)->toBe(0.0)
        ->and($p->lifetime?->withdrawals)->toBe(0.0)
        ->and($p->lifetime?->netCashSpend())->toBe(55.0)
        ->and($p->raw['displayName'])->toBe('jo_b');
});

it('treats a missing lifetime block and empty params as absent, not an error', function () {
    $fixture = playerDetailFixture();
    unset($fixture['lifetime']);
    $fixture['attribution'] = ['utmSource' => 'acme', 'utmMedium' => '', 'utmCampaign' => null, 'acquisitionChannel' => 'UNKNOWN', 'landingUrl' => null, 'params' => []];

    $p = PlayerDetail::fromArray($fixture);

    expect($p->lifetime)->toBeNull()
        ->and($p->attribution->params)->toBe([])
        ->and($p->attribution->utmMedium)->toBeNull()
        ->and($p->attribution->landingUrl)->toBeNull()
        ->and($p->attribution->acquisitionChannel)->toBe(AcquisitionChannel::UNKNOWN);
});

it('parses every documented accountStatus and acquisitionChannel', function () {
    foreach (['DELETED', 'DEACTIVATED', 'GAMSTOP_EXCLUDED', 'SELF_EXCLUDED', 'KYC_FAILED', 'TIMED_OUT', 'ACTIVE'] as $status) {
        expect(AccountStatus::from($status)->value)->toBe($status);
    }

    foreach (['PAID', 'ORGANIC', 'UNKNOWN'] as $channel) {
        expect(AcquisitionChannel::from($channel)->value)->toBe($channel);
    }

    expect(AccountStatus::ACTIVE->isSuppressed())->toBeFalse()
        ->and(AccountStatus::TIMED_OUT->isSuppressed())->toBeTrue()
        ->and(AccountStatus::DELETED->canLogIn())->toBeFalse()
        ->and(AccountStatus::SELF_EXCLUDED->canLogIn())->toBeTrue();
});

// ---------------------------------------------------------------------------
// TIMED_OUT is computed at read time
// ---------------------------------------------------------------------------

it('evaluates a time-out against the timestamp instead of trusting the cached status', function () {
    $until = '2026-10-10T12:00:00Z';

    $timedOut = PlayerDetail::fromArray(playerDetailFixture(1, [
        'accountStatus' => 'TIMED_OUT',
        'suppression' => ['timedOutUntil' => $until],
    ]));

    $before = CarbonImmutable::parse('2026-10-09T12:00:00Z');
    $after = CarbonImmutable::parse('2026-10-10T12:00:01Z');

    expect($timedOut->isTimedOutAt($before))->toBeTrue()
        ->and($timedOut->effectiveStatusAt($before))->toBe(AccountStatus::TIMED_OUT)
        ->and($timedOut->isContactableAt($before))->toBeFalse()
        // The cached status still says TIMED_OUT, but the time-out has lapsed.
        ->and($timedOut->accountStatus)->toBe(AccountStatus::TIMED_OUT)
        ->and($timedOut->isTimedOutAt($after))->toBeFalse()
        ->and($timedOut->effectiveStatusAt($after))->toBe(AccountStatus::ACTIVE)
        ->and($timedOut->isContactableAt($after))->toBeTrue();

    // The boundary instant itself: timedOutUntil is "when it ends".
    expect($timedOut->isTimedOutAt(CarbonImmutable::parse($until)))->toBeFalse();
});

it('promotes a cached ACTIVE player to TIMED_OUT while a later time-out is running', function () {
    $active = PlayerDetail::fromArray(playerDetailFixture(1, [
        'accountStatus' => 'ACTIVE',
        'suppression' => ['timedOutUntil' => '2026-10-10T12:00:00Z'],
    ]));

    expect($active->effectiveStatusAt(CarbonImmutable::parse('2026-10-09')))->toBe(AccountStatus::TIMED_OUT)
        ->and($active->effectiveStatusAt(CarbonImmutable::parse('2026-10-11')))->toBe(AccountStatus::ACTIVE);
});

it('never re-evaluates statuses that rank above TIMED_OUT', function () {
    $excluded = PlayerDetail::fromArray(playerDetailFixture(1, [
        'accountStatus' => 'SELF_EXCLUDED',
        'marketable' => false,
        'suppression' => ['selfExcluded' => true, 'timedOutUntil' => '2026-10-10T12:00:00Z'],
    ]));

    expect($excluded->effectiveStatusAt(CarbonImmutable::parse('2026-10-11')))->toBe(AccountStatus::SELF_EXCLUDED)
        ->and($excluded->isContactableAt(CarbonImmutable::parse('2026-10-11')))->toBeFalse();
});

// ---------------------------------------------------------------------------
// Query building
// ---------------------------------------------------------------------------

it('sends every filter as documented, with repeatable keys and RFC 3339 timestamps', function () {
    $mock = MockClient::global([
        GetPlayersRequest::class => playersPageResponse([], null),
    ]);

    $query = PlayersQuery::make()
        ->limit(50)
        ->createdAfter(CarbonImmutable::parse('2026-09-01T00:00:00+01:00'))
        ->createdBefore('2026-10-01T00:00:00Z')
        ->updatedAfter('2026-09-27T18:04:11Z')
        ->status(AccountStatus::ACTIVE, AccountStatus::TIMED_OUT, AccountStatus::ACTIVE)
        ->acquisitionChannel(AcquisitionChannel::PAID)
        ->marketable()
        ->includeLifetime();

    drawhouse()->playersPage($query, after: '18342');

    $request = sentPendingRequest($mock, 0);
    expect($request->getUrl())->toBe('https://api.test.raffletown.co.uk/v2/partners/players')
        ->and($request->headers()->get('access-token'))->toBe('access-old')
        ->and(sentQueryString($mock, 0))->toBe(
            'limit=50&createdAfter=2026-08-31T23%3A00%3A00Z&createdBefore=2026-10-01T00%3A00%3A00Z&updatedAfter=2026-09-27T18%3A04%3A11Z'
            .'&status=ACTIVE&status=TIMED_OUT&acquisitionChannel=PAID&marketable=true&include=lifetime&after=18342'
        )
        ->and(sentQueryString($mock, 0))->not->toContain('%5B')
        ->and(sentQueryString($mock, 0))->not->toContain('affiliateId');
});

it('defaults to limit=100 with no filters and no after on the first page', function () {
    $mock = MockClient::global([
        GetPlayersRequest::class => playersPageResponse([], null),
    ]);

    drawhouse()->playersPage();

    expect(sentQueryString($mock, 0))->toBe('limit=100');
});

it('clamps limit to 500 client-side and rejects a non-positive limit', function () {
    expect(PlayersQuery::make()->limit(9999)->limit)->toBe(500)
        ->and(PlayersQuery::make()->limit(500)->limit)->toBe(500);

    expect(fn () => PlayersQuery::make()->limit(0))->toThrow(InvalidArgumentException::class);
});

it('rejects createdBefore that is not later than createdAfter before any request is made', function () {
    $mock = MockClient::global([]);

    expect(fn () => PlayersQuery::make()->createdAfter('2026-10-01T00:00:00Z')->createdBefore('2026-10-01T00:00:00Z'))
        ->toThrow(InvalidArgumentException::class, 'createdBefore');

    expect(fn () => PlayersQuery::make()->createdBefore('2026-09-01T00:00:00Z')->createdAfter('2026-10-01T00:00:00Z'))
        ->toThrow(InvalidArgumentException::class, 'createdBefore');

    expect($mock->getRecordedResponses())->toHaveCount(0);
});

it('does not mutate the query: every with* returns a copy', function () {
    $base = PlayersQuery::make();
    $filtered = $base->marketable(false)->status(AccountStatus::ACTIVE);

    expect($base->marketable)->toBeNull()
        ->and($base->statuses)->toBe([])
        ->and($filtered->marketable)->toBeFalse()
        ->and($filtered->toQuery()['marketable'])->toBe('false');
});

// ---------------------------------------------------------------------------
// Cursor paging
// ---------------------------------------------------------------------------

it('walks every page by nextCursor and stops on null, including a filtered short final page', function () {
    $pages = [
        '' => playersPageResponse([playerDetailFixture(1), playerDetailFixture(2), playerDetailFixture(3)], '3'),
        '3' => playersPageResponse([playerDetailFixture(5), playerDetailFixture(8), playerDetailFixture(9)], '9'),
        // Filtered pages are filled, so this short page is genuinely the end.
        '9' => playersPageResponse([playerDetailFixture(12)], null),
    ];

    $mock = MockClient::global([
        GetPlayersRequest::class => fn (PendingRequest $r) => $pages[(string) ($r->query()->get('after') ?? '')],
    ]);

    $players = drawhouse()->allPlayers(PlayersQuery::make()->limit(3)->marketable());

    expect($players)->toBeInstanceOf(LazyCollection::class)
        ->and($mock->getRecordedResponses())->toHaveCount(0);

    $ids = $players->map(fn (PlayerDetail $p) => $p->id)->all();

    expect($ids)->toBe([1, 2, 3, 5, 8, 9, 12])
        ->and(sentRequests($mock))->toHaveCount(3);

    $afters = array_map(fn ($r) => $r->getPendingRequest()->query()->get('after'), $mock->getRecordedResponses());
    expect($afters)->toBe([null, '3', '9']);

    foreach ([0, 1, 2] as $i) {
        expect(sentQueryString($mock, $i))->toStartWith('limit=3&marketable=true');
    }
});

it('is lazy: fetches the second page only once the first is consumed', function () {
    $mock = MockClient::global([
        GetPlayersRequest::class => fn (PendingRequest $r) => $r->query()->get('after') === null
            ? playersPageResponse([playerDetailFixture(1)], '1')
            : playersPageResponse([playerDetailFixture(2)], null),
    ]);

    $first = drawhouse()->allPlayers()->first();

    expect($first?->id)->toBe(1)
        ->and($mock->getRecordedResponses())->toHaveCount(1);
});

it('returns a typed page with nextCursor from playersPage()', function () {
    MockClient::global([
        GetPlayersRequest::class => playersPageResponse([playerDetailFixture(1)], '123'),
    ]);

    $page = drawhouse()->playersPage();

    expect($page)->toBeInstanceOf(PlayerPage::class)
        ->and($page->count())->toBe(1)
        ->and($page->items[0])->toBeInstanceOf(PlayerDetail::class)
        ->and($page->nextCursor)->toBe('123')
        ->and($page->hasMore())->toBeTrue()
        ->and($page->isLastPage())->toBeFalse()
        ->and(PlayerPage::fromArray(['items' => [], 'nextCursor' => null])->isLastPage())->toBeTrue();
});

// ---------------------------------------------------------------------------
// Single player and errors
// ---------------------------------------------------------------------------

it('fetches a single player by id, Player or PlayerDetail', function () {
    $mock = MockClient::global([
        GetPlayerRequest::class => envelope(playerDetailFixture(123)),
    ]);

    $player = drawhouse()->player(123);

    expect($player)->toBeInstanceOf(PlayerDetail::class)
        ->and($player->id)->toBe(123)
        ->and($player->lifetime)->not->toBeNull()
        ->and(sentPendingRequest($mock, 0)->getUrl())->toBe('https://api.test.raffletown.co.uk/v2/partners/players/123');

    expect(drawhouse()->player($player)->id)->toBe(123);
});

it('maps a 404 for a player that is not ours to NotFoundException', function () {
    MockClient::global([
        GetPlayerRequest::class => envelopeError(404, 'Not found', 404),
    ]);

    drawhouse()->player(999);
})->throws(NotFoundException::class);

it('maps the 403 partner_not_live on the feed to PartnerNotLiveException', function () {
    MockClient::global([
        GetPlayersRequest::class => partnerNotLiveResponse('PENDING'),
    ]);

    try {
        drawhouse()->playersPage();
        $this->fail('Expected PartnerNotLiveException');
    } catch (PartnerNotLiveException $e) {
        expect($e->partnerStatus)->toBe('PENDING');
    }
});

it('maps a 400 on the feed to InvalidRequestException', function () {
    MockClient::global([
        GetPlayersRequest::class => envelopeError(7, 'after must be an integer', 400),
    ]);

    drawhouse()->playersPage(after: 'abc');
})->throws(InvalidRequestException::class, 'after');
