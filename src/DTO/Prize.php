<?php

declare(strict_types=1);

namespace Digitonic\DrawHouse\DTO;

use Digitonic\DrawHouse\Enums\PrizeCategory;
use Digitonic\DrawHouse\Enums\PrizeType;
use Digitonic\DrawHouse\Support\CashAlternative;
use Digitonic\DrawHouse\Support\Parse;

/**
 * A prize pool on a round, as configured on the base game. Per-edition prize
 * renaming is not applied. Ordered by display order, then id.
 */
final readonly class Prize
{
    /**
     * @param  float|null  $amount  Prize value. Omitted when not set.
     * @param  PrizeCategory|null  $category  Omitted when not set.
     * @param  int|null  $totalAvailable  Number of this prize in the pool.
     * @param  int|null  $soldCount  Number already won.
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public ?string $name,
        public ?PrizeType $type,
        public ?float $amount,
        public ?PrizeCategory $category,
        public ?int $totalAvailable,
        public ?int $soldCount,
        public array $raw = [],
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            name: Parse::string($data['name'] ?? null),
            type: Parse::enum(PrizeType::class, $data['type'] ?? null),
            amount: Parse::float($data['amount'] ?? null),
            category: Parse::enum(PrizeCategory::class, $data['category'] ?? null),
            totalAvailable: Parse::int($data['totalAvailable'] ?? null),
            soldCount: Parse::int($data['soldCount'] ?? null),
            raw: $data,
        );
    }

    public function remaining(): ?int
    {
        if ($this->totalAvailable === null || $this->soldCount === null) {
            return null;
        }

        return max(0, $this->totalAvailable - $this->soldCount);
    }

    /**
     * What the winner may take in cash instead, given the game's
     * `physicalToCashPercentage` (`$game->fields->physicalToCashPercentage`,
     * or `Game::cashAlternative()`). Whole pounds; null for a prize that is
     * not physical or a game that offers no cash. See CashAlternative.
     */
    public function cashAlternative(?float $physicalToCashPercentage): ?float
    {
        return CashAlternative::for(
            $this->amount,
            Parse::string($this->raw['category'] ?? null) ?? $this->category?->value,
            $physicalToCashPercentage,
        );
    }
}
