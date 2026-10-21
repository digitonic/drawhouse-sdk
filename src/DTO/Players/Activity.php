<?php

declare(strict_types=1);

namespace Digitonic\DrawHouse\DTO\Players;

use Carbon\CarbonImmutable;
use Digitonic\DrawHouse\Support\Parse;

/**
 * `activity` on a players-feed row. Each timestamp is null until the event
 * has happened.
 *
 * `firstDepositValue` (Partner API v1.4) is the amount of the deposit
 * `firstDepositAt` points at, read from DrawHouse's deposit ledger. It is
 * always present, not behind `include=lifetime`.
 */
final readonly class Activity
{
    /**
     * @param  CarbonImmutable|null  $firstDepositAt  The first successful card deposit. Admin credits and seeding never count.
     * @param  float|null  $firstDepositValue  GBP amount of that deposit; null until the player has deposited.
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public ?CarbonImmutable $lastLoginAt,
        public ?CarbonImmutable $firstPurchaseAt,
        public ?CarbonImmutable $lastPurchaseAt,
        public ?CarbonImmutable $firstDepositAt,
        public ?float $firstDepositValue = null,
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
            firstDepositValue: Parse::float($data['firstDepositValue'] ?? null),
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
