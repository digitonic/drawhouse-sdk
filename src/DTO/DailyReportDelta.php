<?php

declare(strict_types=1);

namespace Digitonic\DrawHouse\DTO;

use Carbon\CarbonImmutable;

/**
 * The per-period change derived from two consecutive daily-report snapshots.
 *
 * /v2/affiliates/reports counts are cumulative, so "how many
 * registered on Tuesday" is Tuesday's snapshot minus Monday's. Each field here
 * is `$to->x - $from->x`, or null when either side is missing.
 *
 * Caveats:
 *  - `active` counts purchases in the CALENDAR MONTH of the report, so it
 *    resets on the 1st; its delta across a month boundary is meaningless.
 *  - A negative delta is possible (an erased player leaves `registrations`,
 *    a KYC change moves `internalMarketable`). It is reported as-is.
 *  - `days` is the gap between the two snapshots. It is 1 for consecutive
 *    days; anything larger means the delta spans more than one day.
 */
final readonly class DailyReportDelta
{
    public function __construct(
        public DailyReport $from,
        public DailyReport $to,
        public ?int $registrations,
        public ?int $played,
        public ?int $active,
        public ?int $internalMarketable,
        public ?int $externalMarketable,
        public ?int $uniqueVisitors,
        public ?int $pageViews,
    ) {}

    public static function between(DailyReport $from, DailyReport $to): self
    {
        $diff = static fn (?int $a, ?int $b): ?int => $a === null || $b === null ? null : $b - $a;

        return new self(
            from: $from,
            to: $to,
            registrations: $diff($from->registrations, $to->registrations),
            played: $diff($from->played, $to->played),
            active: $diff($from->active, $to->active),
            internalMarketable: $diff($from->internalMarketable, $to->internalMarketable),
            externalMarketable: $diff($from->externalMarketable, $to->externalMarketable),
            uniqueVisitors: $diff($from->uniqueVisitors, $to->uniqueVisitors),
            pageViews: $diff($from->pageViews, $to->pageViews),
        );
    }

    /**
     * Pair up consecutive snapshots (ordered by `startAt`) and return one
     * delta per pair. N snapshots give N-1 deltas; the earliest snapshot has
     * nothing to subtract from, so fetch one extra day at the start of the
     * range you care about (DrawHouse::dailyReportDeltas() does that).
     *
     * @param  list<DailyReport>  $snapshots
     * @return list<self>
     */
    public static function fromSnapshots(array $snapshots): array
    {
        $ordered = array_values(array_filter($snapshots, static fn (DailyReport $r): bool => $r->startAt !== null));

        usort($ordered, static fn (DailyReport $a, DailyReport $b): int => $a->startAt <=> $b->startAt);

        $deltas = [];

        for ($i = 1, $n = count($ordered); $i < $n; $i++) {
            $deltas[] = self::between($ordered[$i - 1], $ordered[$i]);
        }

        return $deltas;
    }

    /**
     * The day this delta describes: the later snapshot's day.
     */
    public function day(): ?CarbonImmutable
    {
        return $this->to->startAt;
    }

    /**
     * Whole days between the two snapshots. 1 for consecutive days.
     */
    public function days(): ?int
    {
        if ($this->from->startAt === null || $this->to->startAt === null) {
            return null;
        }

        return (int) $this->from->startAt->diffInDays($this->to->startAt, false);
    }

    /**
     * True when the two snapshots sit in different calendar months, in which
     * case `active` has reset and its delta should be ignored.
     */
    public function crossesMonthBoundary(): bool
    {
        if ($this->from->startAt === null || $this->to->startAt === null) {
            return false;
        }

        return $this->from->startAt->format('Y-m') !== $this->to->startAt->format('Y-m');
    }
}
