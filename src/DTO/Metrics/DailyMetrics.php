<?php

declare(strict_types=1);

namespace Digitonic\DrawHouse\DTO\Metrics;

use Carbon\CarbonImmutable;
use Digitonic\DrawHouse\Enums\MetricsGroupBy;
use Digitonic\DrawHouse\Support\Parse;

/**
 * GET /v2/partners/metrics/daily: the range, day boundary and grouping
 * applied, and one row per day (and group). Days with no activity are
 * omitted, so a row's absence means zeros.
 */
final readonly class DailyMetrics
{
    /**
     * @param  list<DailyMetricsRow>  $rows
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public ?CarbonImmutable $from,
        public ?CarbonImmutable $to,
        public ?string $timezone,
        public ?MetricsGroupBy $groupBy,
        public array $rows,
        public array $raw = [],
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            from: Parse::date($data['from'] ?? null),
            to: Parse::date($data['to'] ?? null),
            timezone: Parse::nonEmptyString($data['timezone'] ?? null),
            groupBy: Parse::enum(MetricsGroupBy::class, $data['groupBy'] ?? null),
            rows: array_map(DailyMetricsRow::fromArray(...), Parse::items($data['rows'] ?? [])),
            raw: $data,
        );
    }
}
