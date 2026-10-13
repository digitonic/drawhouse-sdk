<?php

declare(strict_types=1);

namespace Digitonic\DrawHouse\DTO;

use Carbon\CarbonImmutable;
use Digitonic\DrawHouse\Enums\RaffleEntryType;
use Digitonic\DrawHouse\Support\Parse;

/**
 * `Game.fields` — raffle configuration. Every value arrives as a STRING
 * exactly as stored on the game ("550", "true", "50") and is normalised
 * here. A field that is not set is omitted, so most properties are nullable.
 */
final readonly class GameFields
{
    /**
     * @param  string|null  $revealMechanic  How instant-win tickets are revealed, e.g. SCAN.
     * @param  float|null  $physicalToCashPercentage  Omitted when cash-for-physical is not offered.
     * @param  float|null  $physicalToSiteCreditPercentage  Omitted when not offered.
     * @param  float|null  $cashToSiteCreditPercentage  Omitted when not offered.
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public ?RaffleEntryType $raffleEntryType,
        public ?string $revealMechanic,
        public ?bool $hasCashPrize,
        public ?int $maxTickets,
        public ?int $maxTicketsPerPlayer,
        public ?int $defaultCheckoutTicketCount,
        public ?int $entriesCount,
        public ?CarbonImmutable $scheduledDrawTime,
        public ?float $rafflePrizePercentage,
        public ?float $physicalToCashPercentage,
        public ?float $physicalToSiteCreditPercentage,
        public ?float $cashToSiteCreditPercentage,
        public ?bool $isRingFenced,
        public array $raw = [],
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            raffleEntryType: Parse::enum(RaffleEntryType::class, $data['raffleEntryType'] ?? null),
            revealMechanic: Parse::string($data['revealMechanic'] ?? null),
            hasCashPrize: Parse::bool($data['hasCashPrize'] ?? null),
            maxTickets: Parse::int($data['maxTickets'] ?? null),
            maxTicketsPerPlayer: Parse::int($data['maxTicketsPerPlayer'] ?? null),
            defaultCheckoutTicketCount: Parse::int($data['defaultCheckoutTicketCount'] ?? null),
            entriesCount: Parse::int($data['entriesCount'] ?? null),
            scheduledDrawTime: Parse::dateTime($data['scheduledDrawTime'] ?? null),
            rafflePrizePercentage: Parse::float($data['rafflePrizePercentage'] ?? null),
            physicalToCashPercentage: Parse::float($data['physicalToCashPercentage'] ?? null),
            physicalToSiteCreditPercentage: Parse::float($data['physicalToSiteCreditPercentage'] ?? null),
            cashToSiteCreditPercentage: Parse::float($data['cashToSiteCreditPercentage'] ?? null),
            isRingFenced: Parse::bool($data['isRingFenced'] ?? null),
            raw: $data,
        );
    }

    /**
     * Typed access to any field DrawHouse adds that is not modelled above.
     */
    public function string(string $key): ?string
    {
        return Parse::string($this->raw[$key] ?? null);
    }

    public function float(string $key): ?float
    {
        return Parse::float($this->raw[$key] ?? null);
    }

    public function int(string $key): ?int
    {
        return Parse::int($this->raw[$key] ?? null);
    }

    public function bool(string $key): ?bool
    {
        return Parse::bool($this->raw[$key] ?? null);
    }
}
