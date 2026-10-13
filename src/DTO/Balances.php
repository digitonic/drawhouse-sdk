<?php

declare(strict_types=1);

namespace Digitonic\DrawHouse\DTO;

use Digitonic\DrawHouse\Support\Parse;

/**
 * GET /v2/players/balances — aggregate balances held across your players'
 * wallets. A single object, all NUMBERS on the wire.
 */
final readonly class Balances
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public ?float $cashBalance,
        public ?float $siteCreditBalance,
        public ?float $vaultKeyBalance,
        public array $raw = [],
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            cashBalance: Parse::float($data['cashBalance'] ?? null),
            siteCreditBalance: Parse::float($data['siteCreditBalance'] ?? null),
            vaultKeyBalance: Parse::float($data['vaultKeyBalance'] ?? null),
            raw: $data,
        );
    }
}
