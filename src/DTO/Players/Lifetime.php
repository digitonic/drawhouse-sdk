<?php

declare(strict_types=1);

namespace Digitonic\DrawHouse\DTO\Players;

use Digitonic\DrawHouse\Support\Parse;

/**
 * `lifetime` on a players-feed row. ALL AMOUNTS ARE GBP. Present only when
 * the list was requested with `include=lifetime`; always present on /{id}.
 */
final readonly class Lifetime
{
    /**
     * @param  int|null  $depositCount  Successful card deposits. Admin credits and seeding excluded.
     * @param  float|null  $depositValue  Value of those deposits.
     * @param  float|null  $cashSpend  Wallet-cash part of every successful checkout, refunded ones included.
     * @param  float|null  $siteCreditSpend  Site-credit part of the same checkouts.
     * @param  float|null  $refunds  Cash paid back for cancelled rounds or on exclusion.
     * @param  float|null  $cashPrizesWon  Cash prizes paid to the wallet. Site-credit prizes excluded.
     * @param  float|null  $withdrawals  Approved or paid-out withdrawals.
     * @param  float|null  $netRevenue  `cashSpend − refunds − cashPrizesWon` (Partner API v1.4): what the player is worth in cash. Site credit and withdrawals are not in it.
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public ?int $depositCount,
        public ?float $depositValue,
        public ?float $cashSpend,
        public ?float $siteCreditSpend,
        public ?float $refunds,
        public ?float $cashPrizesWon,
        public ?float $withdrawals,
        public ?float $netRevenue = null,
        public array $raw = [],
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            depositCount: Parse::int($data['depositCount'] ?? null),
            depositValue: Parse::float($data['depositValue'] ?? null),
            cashSpend: Parse::float($data['cashSpend'] ?? null),
            siteCreditSpend: Parse::float($data['siteCreditSpend'] ?? null),
            refunds: Parse::float($data['refunds'] ?? null),
            cashPrizesWon: Parse::float($data['cashPrizesWon'] ?? null),
            withdrawals: Parse::float($data['withdrawals'] ?? null),
            netRevenue: Parse::float($data['netRevenue'] ?? null),
            raw: $data,
        );
    }

    /**
     * Cash spend net of refunds, GBP. Null when either side is missing.
     */
    public function netCashSpend(): ?float
    {
        if ($this->cashSpend === null || $this->refunds === null) {
            return null;
        }

        return $this->cashSpend - $this->refunds;
    }
}
