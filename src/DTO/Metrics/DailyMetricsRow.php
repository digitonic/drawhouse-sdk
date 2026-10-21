<?php

declare(strict_types=1);

namespace Digitonic\DrawHouse\DTO\Metrics;

use Carbon\CarbonImmutable;
use Digitonic\DrawHouse\Support\Parse;

/**
 * One day (and group) of GET /v2/partners/metrics/daily (Partner API v1.4).
 *
 * PER-DAY values, not cumulative snapshots like the daily reports. Money is
 * GBP and follows the players feed's `lifetime` rules: successful card
 * deposits only, admin credits kept apart, and prizes paid in cash and in
 * site credit reported separately. `netCashRevenue` is
 * `cashSpend − refunds − cashPrizesPaid`. With `groupBy=edition` only the
 * ticket figures are populated.
 */
final readonly class DailyMetricsRow
{
    /**
     * @param  CarbonImmutable|null  $day  The calendar day, in the request's timezone.
     * @param  DailyMetricsGroup|null  $group  Present with `groupBy=source` or `groupBy=edition`.
     * @param  int|null  $verified  Registrations that day whose identity check is VERIFIED now.
     * @param  int|null  $firstDeposits  Players whose first successful card deposit landed that day.
     * @param  float|null  $ticketRevenue  Sum of ticket prices, however they were paid.
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public ?CarbonImmutable $day,
        public ?DailyMetricsGroup $group,
        public ?int $registrations,
        public ?int $verified,
        public ?int $firstDeposits,
        public ?int $depositors,
        public ?int $depositCount,
        public ?float $depositValue,
        public ?float $adminCredit,
        public ?int $purchasers,
        public ?int $checkouts,
        public ?int $ticketsSold,
        public ?float $ticketRevenue,
        public ?float $cashSpend,
        public ?float $siteCreditSpend,
        public ?float $refunds,
        public ?float $cashPrizesPaid,
        public ?float $siteCreditPrizesAwarded,
        public ?float $withdrawals,
        public ?float $netCashRevenue,
        public array $raw = [],
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            day: Parse::date($data['day'] ?? null),
            group: is_array($data['group'] ?? null) ? DailyMetricsGroup::fromArray($data['group']) : null,
            registrations: Parse::int($data['registrations'] ?? null),
            verified: Parse::int($data['verified'] ?? null),
            firstDeposits: Parse::int($data['firstDeposits'] ?? null),
            depositors: Parse::int($data['depositors'] ?? null),
            depositCount: Parse::int($data['depositCount'] ?? null),
            depositValue: Parse::float($data['depositValue'] ?? null),
            adminCredit: Parse::float($data['adminCredit'] ?? null),
            purchasers: Parse::int($data['purchasers'] ?? null),
            checkouts: Parse::int($data['checkouts'] ?? null),
            ticketsSold: Parse::int($data['ticketsSold'] ?? null),
            ticketRevenue: Parse::float($data['ticketRevenue'] ?? null),
            cashSpend: Parse::float($data['cashSpend'] ?? null),
            siteCreditSpend: Parse::float($data['siteCreditSpend'] ?? null),
            refunds: Parse::float($data['refunds'] ?? null),
            cashPrizesPaid: Parse::float($data['cashPrizesPaid'] ?? null),
            siteCreditPrizesAwarded: Parse::float($data['siteCreditPrizesAwarded'] ?? null),
            withdrawals: Parse::float($data['withdrawals'] ?? null),
            netCashRevenue: Parse::float($data['netCashRevenue'] ?? null),
            raw: $data,
        );
    }
}
