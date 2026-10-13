<?php

declare(strict_types=1);

namespace Digitonic\DrawHouse\DTO\LiveDraws;

use Digitonic\DrawHouse\Enums\PrizeCategory;
use Digitonic\DrawHouse\Enums\PrizeType;
use Digitonic\DrawHouse\Support\Parse;

/**
 * A prize on a live draw, with YOUR edition's name, description and image
 * already applied (unlike Prize on the games catalogue).
 */
final readonly class LivePrize
{
    /**
     * @param  PrizeType|null  $type  DRAW or INSTANT.
     * @param  PrizeCategory|null  $category  PHYSICAL, CASH, SITE_CREDIT or VOUCHER when set.
     * @param  float|null  $amount  Prize value. Omitted when not set.
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public ?int $prizePoolId,
        public ?string $name,
        public ?string $description,
        public ?PrizeType $type,
        public ?PrizeCategory $category,
        public ?float $amount,
        public ?string $imageUrl,
        public ?int $totalAvailable,
        public ?int $displayOrder,
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
            description: Parse::nonEmptyString($data['description'] ?? null),
            type: Parse::enum(PrizeType::class, $data['type'] ?? null),
            category: Parse::enum(PrizeCategory::class, $data['category'] ?? null),
            amount: Parse::float($data['amount'] ?? null),
            imageUrl: Parse::nonEmptyString($data['imageUrl'] ?? null),
            totalAvailable: Parse::int($data['totalAvailable'] ?? null),
            displayOrder: Parse::int($data['displayOrder'] ?? null),
            raw: $data,
        );
    }

    public function isInstant(): bool
    {
        return $this->type?->isInstant() ?? false;
    }
}
