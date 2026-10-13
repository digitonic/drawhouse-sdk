<?php

declare(strict_types=1);

namespace Digitonic\DrawHouse\DTO\LiveDraws;

use Digitonic\DrawHouse\Support\Parse;

/**
 * One entry of `instantWins.perPrize`.
 */
final readonly class InstantWinPrize
{
    /**
     * @param  int|null  $total  Number of this prize in the pool.
     * @param  int|null  $remaining  Still winnable.
     * @param  int|null  $revealed  Won and revealed.
     * @param  int|null  $claimed  Won and claimed.
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public ?int $prizePoolId,
        public ?string $name,
        public ?float $amount,
        public ?int $total,
        public ?int $remaining,
        public ?int $revealed,
        public ?int $claimed,
        public array $raw = [],
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            prizePoolId: Parse::int($data['prizePoolId'] ?? null),
            name: Parse::string($data['name'] ?? null),
            amount: Parse::float($data['amount'] ?? null),
            total: Parse::int($data['total'] ?? null),
            remaining: Parse::int($data['remaining'] ?? null),
            revealed: Parse::int($data['revealed'] ?? null),
            claimed: Parse::int($data['claimed'] ?? null),
            raw: $data,
        );
    }
}
