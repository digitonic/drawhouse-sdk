<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Digitonic\DrawHouse\DTO\DailyReport;
use Digitonic\DrawHouse\DTO\DailyReportDelta;
use Digitonic\DrawHouse\Enums\ReportInterval;
use Digitonic\DrawHouse\Http\Requests\GetAffiliateReportsRequest;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\PendingRequest;

beforeEach(fn () => seedTokens());

it('parses the v1.2 daily report fields', function () {
    MockClient::global([
        GetAffiliateReportsRequest::class => dataOnly([dailyReportFixture()]),
    ]);

    $report = drawhouse()->dailyReports('2026-08-01', '2026-08-01')[0];

    expect($report->registrations)->toBe(210)
        ->and($report->played)->toBe(151)
        ->and($report->active)->toBe(160)
        ->and($report->internalMarketable)->toBe(120)
        ->and($report->externalMarketable)->toBe(95)
        ->and($report->uniqueVisitors)->toBe(2400)
        ->and($report->pageViews)->toBe(6100);
});

it('tolerates a v1.0-shaped report without the new fields', function () {
    $report = DailyReport::fromArray(['id' => 1, 'registrations' => 5, 'played' => 2, 'startAt' => '2026-08-01T00:00:00Z']);

    expect($report->active)->toBeNull()
        ->and($report->pageViews)->toBeNull();
});

it('sends interval=weeks when asked', function () {
    $mock = MockClient::global([
        GetAffiliateReportsRequest::class => dataOnly([]),
    ]);

    drawhouse()->dailyReports('2026-08-01', '2026-08-31', ReportInterval::WEEKS);

    expect(sentQueryString($mock, 0))->toBe('interval=weeks&start=2026-08-01&end=2026-08-31');
});

it('derives per-day deltas from consecutive cumulative snapshots', function () {
    $mon = DailyReport::fromArray(dailyReportFixture(['id' => 1, 'registrations' => 200, 'played' => 150, 'active' => 100, 'startAt' => '2026-08-03T00:00:00Z']));
    $tue = DailyReport::fromArray(dailyReportFixture(['id' => 2, 'registrations' => 210, 'played' => 151, 'active' => 104, 'pageViews' => 6200, 'startAt' => '2026-08-04T00:00:00Z']));
    $wed = DailyReport::fromArray(dailyReportFixture(['id' => 3, 'registrations' => 209, 'played' => 160, 'active' => 110, 'pageViews' => 6300, 'startAt' => '2026-08-05T00:00:00Z']));

    // Out of order on purpose: deltas are paired by startAt, not input order.
    $deltas = DailyReportDelta::fromSnapshots([$wed, $mon, $tue]);

    expect($deltas)->toHaveCount(2)
        ->and($deltas[0]->day()?->toDateString())->toBe('2026-08-04')
        ->and($deltas[0]->days())->toBe(1)
        ->and($deltas[0]->registrations)->toBe(10)
        ->and($deltas[0]->played)->toBe(1)
        ->and($deltas[0]->active)->toBe(4)
        ->and($deltas[0]->pageViews)->toBe(100)
        ->and($deltas[0]->uniqueVisitors)->toBe(0)
        ->and($deltas[0]->crossesMonthBoundary())->toBeFalse()
        // An erased player can make a count go down; it is reported as-is.
        ->and($deltas[1]->registrations)->toBe(-1)
        ->and($deltas[1]->played)->toBe(9)
        ->and($deltas[1]->from)->toBe($tue)
        ->and($deltas[1]->to)->toBe($wed);

    expect($tue->deltaFrom($mon)->registrations)->toBe(10);
});

it('flags a delta that crosses a calendar month, where active has reset', function () {
    $last = DailyReport::fromArray(dailyReportFixture(['active' => 400, 'startAt' => '2026-08-31T00:00:00Z']));
    $first = DailyReport::fromArray(dailyReportFixture(['active' => 12, 'startAt' => '2026-09-01T00:00:00Z']));

    $delta = $first->deltaFrom($last);

    expect($delta->crossesMonthBoundary())->toBeTrue()
        ->and($delta->active)->toBe(-388);
});

it('reports a multi-day gap and null deltas for missing fields', function () {
    $a = DailyReport::fromArray(['id' => 1, 'registrations' => 10, 'played' => 2, 'startAt' => '2026-08-01T00:00:00Z']);
    $b = DailyReport::fromArray(['id' => 2, 'registrations' => 15, 'played' => 2, 'startAt' => '2026-08-04T00:00:00Z']);

    $delta = $b->deltaFrom($a);

    expect($delta->days())->toBe(3)
        ->and($delta->registrations)->toBe(5)
        ->and($delta->active)->toBeNull();
});

it('fetches one extra day so dailyReportDeltas() covers the whole requested range', function () {
    $mock = MockClient::global([
        GetAffiliateReportsRequest::class => fn (PendingRequest $r) => dataOnly([
            dailyReportFixture(['id' => 1, 'registrations' => 100, 'startAt' => '2026-08-01T00:00:00Z']),
            dailyReportFixture(['id' => 2, 'registrations' => 130, 'startAt' => '2026-08-02T00:00:00Z']),
            dailyReportFixture(['id' => 3, 'registrations' => 135, 'startAt' => '2026-08-03T00:00:00Z']),
        ]),
    ]);

    $deltas = drawhouse()->dailyReportDeltas(CarbonImmutable::parse('2026-08-02'), '2026-08-03');

    expect(sentQueryString($mock, 0))->toBe('interval=days&start=2026-08-01&end=2026-08-03')
        ->and($deltas)->toHaveCount(2)
        ->and($deltas[0]->day()?->toDateString())->toBe('2026-08-02')
        ->and($deltas[0]->registrations)->toBe(30)
        ->and($deltas[1]->registrations)->toBe(5);
});

it('ignores snapshots outside the requested window when deriving deltas, since the API may ignore start/end', function () {
    MockClient::global([
        GetAffiliateReportsRequest::class => dataOnly([
            dailyReportFixture(['id' => 1, 'registrations' => 50, 'startAt' => '2026-07-01T00:00:00Z']),
            dailyReportFixture(['id' => 2, 'registrations' => 100, 'startAt' => '2026-08-01T00:00:00Z']),
            dailyReportFixture(['id' => 3, 'registrations' => 130, 'startAt' => '2026-08-02T00:00:00Z']),
            dailyReportFixture(['id' => 4, 'registrations' => 135, 'startAt' => '2026-08-03T00:00:00Z']),
            dailyReportFixture(['id' => 5, 'registrations' => 999, 'startAt' => '2026-09-01T00:00:00Z']),
        ]),
    ]);

    $deltas = drawhouse()->dailyReportDeltas('2026-08-02', '2026-08-03');

    expect($deltas)->toHaveCount(2)
        ->and($deltas[0]->day()?->toDateString())->toBe('2026-08-02')
        ->and($deltas[0]->registrations)->toBe(30)
        ->and($deltas[1]->day()?->toDateString())->toBe('2026-08-03')
        ->and($deltas[1]->registrations)->toBe(5);
});
