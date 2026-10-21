<?php

declare(strict_types=1);

namespace Digitonic\DrawHouse\DTO\Players;

use Digitonic\DrawHouse\Support\Parse;

/**
 * `balances` on a players-feed row (Partner API v1.4): the player's money
 * right now, not a lifetime total. GBP. Present only with `include=lifetime`
 * on the list; always on /{id}.
 *
 * Not to be confused with DTO\Balances, the aggregate across every player
 * from GET /v2/players/balances.
 */
final readonly class WalletBalances
{
    /**
     * @param  float|null  $cash  Withdrawable wallet balance.
     * @param  float|null  $siteCredit  Unexpired site credit: spendable on tickets only, never withdrawable.
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public ?float $cash,
        public ?float $siteCredit,
        public array $raw = [],
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            cash: Parse::float($data['cash'] ?? null),
            siteCredit: Parse::float($data['siteCredit'] ?? null),
            raw: $data,
        );
    }
}
