<?php

declare(strict_types=1);

namespace Digitonic\DrawHouse\DTO;

use Carbon\CarbonImmutable;
use Digitonic\DrawHouse\Support\Parse;

/**
 * One item of GET /v2/affiliates/reports?interval=days|weeks.
 *
 * CUMULATIVE SNAPSHOTS, NOT PER-DAY DELTAS.
 * Every count is the position as at the end of the report day: `registrations`
 * is every player registered under the affiliate up to that day, `played` is
 * how many of them have EVER bought an entry, and so on. For a day's new
 * registrations subtract the previous day's snapshot — see deltaFrom() and
 * DailyReportDelta::fromSnapshots().
 *
 * `registrations` and `played` are the figures DrawHouse say to reconcile
 * against; the other counts are informational.
 *
 * A daily job records the PREVIOUS day, so today's figures appear tomorrow.
 * `id` is the `dailyReportId` accepted by the UTM breakdown endpoint.
 */
final readonly class DailyReport
{
    /**
     * @param  int|null  $registrations  Players registered under the affiliate up to the end of the day.
     * @param  int|null  $played  Of those, players who have ever bought at least one entry.
     * @param  int|null  $active  Of those, players who bought an entry in the calendar month of the report.
     * @param  int|null  $internalMarketable  Of those, players with email or SMS marketing opt-in.
     * @param  int|null  $externalMarketable  Of those, players who have opted in to partner offers.
     * @param  int|null  $uniqueVisitors  All-time site traffic for the brand. 0 when analytics is not configured.
     * @param  int|null  $pageViews  All-time site traffic for the brand. 0 when analytics is not configured.
     * @param  CarbonImmutable|null  $startAt  Start of the day the snapshot covers (UTC).
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public ?int $id,
        public ?int $registrations,
        public ?int $played,
        public ?CarbonImmutable $startAt,
        public ?int $active = null,
        public ?int $internalMarketable = null,
        public ?int $externalMarketable = null,
        public ?int $uniqueVisitors = null,
        public ?int $pageViews = null,
        public array $raw = [],
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: Parse::int($data['id'] ?? null),
            registrations: Parse::int($data['registrations'] ?? null),
            played: Parse::int($data['played'] ?? null),
            startAt: Parse::dateTime($data['startAt'] ?? null),
            active: Parse::int($data['active'] ?? null),
            internalMarketable: Parse::int($data['internalMarketable'] ?? null),
            externalMarketable: Parse::int($data['externalMarketable'] ?? null),
            uniqueVisitors: Parse::int($data['uniqueVisitors'] ?? null),
            pageViews: Parse::int($data['pageViews'] ?? null),
            raw: $data,
        );
    }

    /**
     * The change between an earlier snapshot and this one, i.e. the activity
     * in the period between them. With consecutive daily snapshots that is
     * one day's new registrations, new first-time players, and so on.
     */
    public function deltaFrom(self $previous): DailyReportDelta
    {
        return DailyReportDelta::between($previous, $this);
    }
}
