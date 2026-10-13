<?php

declare(strict_types=1);

namespace Digitonic\DrawHouse\DTO\Players;

use Carbon\CarbonImmutable;
use Digitonic\DrawHouse\Support\Parse;

/**
 * `activity` on a players-feed row. Each timestamp is null until the event
 * has happened.
 */
final readonly class Activity
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public ?CarbonImmutable $lastLoginAt,
        public ?CarbonImmutable $firstPurchaseAt,
        public ?CarbonImmutable $lastPurchaseAt,
        public ?CarbonImmutable $firstDepositAt,
        public array $raw = [],
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            lastLoginAt: Parse::dateTime($data['lastLoginAt'] ?? null),
            firstPurchaseAt: Parse::dateTime($data['firstPurchaseAt'] ?? null),
            lastPurchaseAt: Parse::dateTime($data['lastPurchaseAt'] ?? null),
            firstDepositAt: Parse::dateTime($data['firstDepositAt'] ?? null),
            raw: $data,
        );
    }

    public function hasPurchased(): bool
    {
        return $this->firstPurchaseAt !== null;
    }

    public function hasDeposited(): bool
    {
        return $this->firstDepositAt !== null;
    }
}
