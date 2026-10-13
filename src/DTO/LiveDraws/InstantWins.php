<?php

declare(strict_types=1);

namespace Digitonic\DrawHouse\DTO\LiveDraws;

use Digitonic\DrawHouse\Support\Parse;

/**
 * `instantWins` on a live draw. ONLY PRESENT when the round has instant-win
 * prizes (LiveDraw::$instantWins is null otherwise). `remaining` is what can
 * still be won.
 */
final readonly class InstantWins
{
    /**
     * @param  list<InstantWinPrize>  $perPrize
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public ?bool $enabled,
        public ?int $totalPrizes,
        public ?int $remaining,
        public ?int $revealed,
        public ?int $claimed,
        public ?float $totalValue,
        public array $perPrize,
        public array $raw = [],
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            enabled: Parse::bool($data['enabled'] ?? null),
            totalPrizes: Parse::int($data['totalPrizes'] ?? null),
            remaining: Parse::int($data['remaining'] ?? null),
            revealed: Parse::int($data['revealed'] ?? null),
            claimed: Parse::int($data['claimed'] ?? null),
            totalValue: Parse::float($data['totalValue'] ?? null),
            perPrize: array_map(InstantWinPrize::fromArray(...), Parse::items($data['perPrize'] ?? [])),
            raw: $data,
        );
    }

    public function hasRemaining(): bool
    {
        return ($this->remaining ?? 0) > 0;
    }
}
