<?php

declare(strict_types=1);

use Digitonic\DrawHouse\DTO\Money\MoneyEvent;
use Digitonic\DrawHouse\Enums\AcquisitionChannel;
use Digitonic\DrawHouse\Enums\MetricsGroupBy;
use Digitonic\DrawHouse\Enums\MetricsTimezone;
use Digitonic\DrawHouse\Enums\MoneyKind;
use Digitonic\DrawHouse\Http\Requests\GetDailyMetricsRequest;
use Digitonic\DrawHouse\Http\Requests\GetTransactionsRequest;
use Digitonic\DrawHouse\Support\TransactionsQuery;
use Saloon\Http\Faking\MockClient;

beforeEach(fn () => seedTokens());

/**
 * A money-feed row.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function moneyEventFixture(array $overrides = []): array
{
    return [
        'id' => 'PURCHASE:55012',
        'kind' => 'PURCHASE',
        'playerId' => 123,
        'amount' => 6,
        'currency' => 'GBP',
        'status' => 'SUCCEEDED',
        'cashAmount' => 4,
        'siteCreditAmount' => 2,
        'gameId' => 61,
        'roundId' => 1180,
        'checkoutId' => 55012,
        'reference' => 'chk_55012',
        'createdAt' => '2026-10-20T19:02:11Z',
        'updatedAt' => '2026-10-20T19:02:11Z',
        ...$overrides,
    ];
}

it('parses every field of a money-feed row', function () {
    MockClient::global([GetTransactionsRequest::class => envelope(['items' => [moneyEventFixture()], 'nextCursor' => null])]);

    $event = drawhouse()->transactionsPage()->items[0];

    expect($event)->toBeInstanceOf(MoneyEvent::class)
        ->and($event->id)->toBe('PURCHASE:55012')
        ->and($event->kind)->toBe(MoneyKind::PURCHASE)
        ->and($event->playerId)->toBe(123)
        ->and($event->amount)->toBe(6.0)
        ->and($event->currency)->toBe('GBP')
        ->and($event->cashAmount)->toBe(4.0)
        ->and($event->siteCreditAmount)->toBe(2.0)
        ->and($event->gameId)->toBe(61)
        ->and($event->roundId)->toBe(1180)
        ->and($event->checkoutId)->toBe(55012)
        ->and($event->reference)->toBe('chk_55012')
        ->and($event->createdAt?->toIso8601ZuluString())->toBe('2026-10-20T19:02:11Z');
});

it('counts money only on settled rows', function (array $row, bool $settled) {
    expect(MoneyEvent::fromArray(moneyEventFixture($row))->isSettled())->toBe($settled);
})->with([
    'a successful deposit' => [['kind' => 'DEPOSIT', 'status' => 'SUCCESS'], true],
    'a deposit still processing' => [['kind' => 'DEPOSIT', 'status' => 'PROCESSING'], false],
    'a failed deposit' => [['kind' => 'DEPOSIT', 'status' => 'FAILED'], false],
    'a purchase' => [['kind' => 'PURCHASE', 'status' => 'SUCCEEDED'], true],
    'a refunded purchase' => [['kind' => 'PURCHASE', 'status' => 'REFUNDED'], true],
    'an approved withdrawal' => [['kind' => 'WITHDRAWAL', 'status' => 'ADMIN_APPROVED'], true],
    'a withdrawal awaiting approval' => [['kind' => 'WITHDRAWAL', 'status' => 'PENDING_APPROVAL'], false],
    'a refund' => [['kind' => 'REFUND', 'status' => 'SUCCEEDED'], true],
    'a site-credit award' => [['kind' => 'SITE_CREDIT_AWARD', 'status' => 'PROMO'], true],
    'a kind this SDK does not know' => [['kind' => 'SOMETHING_NEW', 'status' => 'SUCCESS'], false],
]);

it('keeps the wire value of a kind it does not know', function () {
    $event = MoneyEvent::fromArray(moneyEventFixture(['kind' => 'SOMETHING_NEW']));

    expect($event->kind)->toBeNull()
        ->and($event->kindName)->toBe('SOMETHING_NEW');
});

it('sends every money-feed filter and passes the opaque cursor back verbatim', function () {
    $mock = MockClient::global([GetTransactionsRequest::class => envelope(['items' => [], 'nextCursor' => null])]);

    drawhouse()->transactionsPage(
        TransactionsQuery::make()->kind(MoneyKind::DEPOSIT, MoneyKind::REFUND)->status('success')->player(123)->updatedAfter('2026-10-01T00:00:00Z')->limit(800),
        after: 'MjAyNi0xMC0wMVQxMDoxMjowMFp8REVQT1NJVHw5OTEy',
    );

    expect(sentPendingRequest($mock, 0)->getUrl())->toBe('https://api.test.raffletown.co.uk/v2/partners/transactions')
        ->and(sentQueryString($mock, 0))->toBe('limit=500&updatedAfter=2026-10-01T00%3A00%3A00Z&kind=DEPOSIT&kind=REFUND&status=SUCCESS&playerId=123&after=MjAyNi0xMC0wMVQxMDoxMjowMFp8REVQT1NJVHw5OTEy');
});

it('reads one player\'s money feed from their own route, without the player filter', function () {
    $mock = MockClient::global([GetTransactionsRequest::class => envelope(['items' => [moneyEventFixture()], 'nextCursor' => null])]);

    drawhouse()->playerTransactionsPage(123, TransactionsQuery::make()->player(999)->kind(MoneyKind::PURCHASE));

    expect(sentPendingRequest($mock, 0)->getUrl())->toBe('https://api.test.raffletown.co.uk/v2/partners/players/123/transactions')
        ->and(sentQueryString($mock, 0))->toBe('limit=100&kind=PURCHASE');
});

it('walks every page of the money feed lazily, for everyone or one player', function (?int $player) {
    $mock = MockClient::global([
        envelope(['items' => [moneyEventFixture(['id' => 'DEPOSIT:1']), moneyEventFixture(['id' => 'DEPOSIT:2'])], 'nextCursor' => 'abc']),
        envelope(['items' => [moneyEventFixture(['id' => 'DEPOSIT:3'])], 'nextCursor' => null]),
    ]);

    $events = $player === null ? drawhouse()->allTransactions() : drawhouse()->allPlayerTransactions($player);

    expect($events->map(fn (MoneyEvent $e) => $e->id)->all())->toBe(['DEPOSIT:1', 'DEPOSIT:2', 'DEPOSIT:3'])
        ->and(sentQueryString($mock, 1))->toEndWith('after=abc');
})->with([
    'every player' => [null],
    'one player' => [123],
]);

it('reads per-day metrics, grouped by source', function () {
    $mock = MockClient::global([GetDailyMetricsRequest::class => envelope([
        'from' => '2026-10-01',
        'to' => '2026-10-02',
        'timezone' => 'Europe/London',
        'groupBy' => 'source',
        'rows' => [[
            'day' => '2026-10-01',
            'group' => ['utmSource' => 'joe', 'utmMedium' => 'email', 'utmCampaign' => 'oct-launch', 'acquisitionChannel' => 'ORGANIC'],
            'registrations' => 12, 'verified' => 10, 'firstDeposits' => 4, 'depositors' => 5, 'depositCount' => 6,
            'depositValue' => 120.5, 'adminCredit' => 0, 'purchasers' => 7, 'checkouts' => 9, 'ticketsSold' => 30,
            'ticketRevenue' => 45, 'cashSpend' => 40, 'siteCreditSpend' => 5, 'refunds' => 2, 'cashPrizesPaid' => 10,
            'siteCreditPrizesAwarded' => 3, 'withdrawals' => 0, 'netCashRevenue' => 28,
        ]],
    ])]);

    $metrics = drawhouse()->dailyMetrics('2026-10-01', '2026-10-02', MetricsTimezone::UTC, MetricsGroupBy::SOURCE);
    $row = $metrics->rows[0];

    expect($metrics->groupBy)->toBe(MetricsGroupBy::SOURCE)
        ->and($metrics->from?->toDateString())->toBe('2026-10-01')
        ->and($row->day?->toDateString())->toBe('2026-10-01')
        ->and($row->group?->utmCampaign)->toBe('oct-launch')
        ->and($row->group?->acquisitionChannel)->toBe(AcquisitionChannel::ORGANIC)
        ->and($row->firstDeposits)->toBe(4)
        ->and($row->depositValue)->toBe(120.5)
        ->and($row->ticketsSold)->toBe(30)
        ->and($row->netCashRevenue)->toBe(28.0)
        ->and(sentPendingRequest($mock, 0)->getUrl())->toBe('https://api.test.raffletown.co.uk/v2/partners/metrics/daily')
        ->and(sentQueryString($mock, 0))->toBe('from=2026-10-01&to=2026-10-02&timezone=UTC&groupBy=source');
});

it('refuses a metrics range the API would answer 400 for before sending anything', function (string $from, string $to, string $message) {
    $mock = MockClient::global([]);

    expect(fn () => drawhouse()->dailyMetrics($from, $to))->toThrow(InvalidArgumentException::class, $message)
        ->and($mock->getRecordedResponses())->toBeEmpty();
})->with([
    'to before from' => ['2026-10-02', '2026-10-01', 'must not be before'],
    'over 366 days' => ['2025-01-01', '2026-10-01', 'limited to 366 days'],
]);
