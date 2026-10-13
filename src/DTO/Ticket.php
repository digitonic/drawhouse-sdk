<?php

declare(strict_types=1);

namespace Digitonic\DrawHouse\DTO;

use Carbon\CarbonImmutable;
use Digitonic\DrawHouse\Support\Parse;

/**
 * One item of GET /v2/games/tickets?gameId= (`data.tickets`).
 *
 * `price` is a NUMBER on the wire and is the amount actually paid, which can
 * be below the round's base price for discounted batches. UTM fields are
 * empty strings when the purchase did not follow a campaign link; they are
 * normalised to null here.
 */
final readonly class Ticket
{
    /**
     * @param  int|null  $playerId  Matches `id` in the players report.
     * @param  string|null  $paymentStatus  e.g. SUCCEEDED.
     * @param  CarbonImmutable|null  $createdAt  Time of purchase.
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public ?int $id,
        public ?int $playerId,
        public ?float $price,
        public ?string $paymentStatus,
        public ?CarbonImmutable $createdAt,
        public ?string $utmSource,
        public ?string $utmMedium,
        public ?string $utmCampaign,
        public array $raw = [],
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: Parse::int($data['id'] ?? null),
            playerId: Parse::int($data['playerId'] ?? null),
            price: Parse::float($data['price'] ?? null),
            paymentStatus: Parse::string($data['paymentStatus'] ?? null),
            createdAt: Parse::dateTime($data['createdAt'] ?? null),
            utmSource: Parse::nonEmptyString($data['utmSource'] ?? null),
            utmMedium: Parse::nonEmptyString($data['utmMedium'] ?? null),
            utmCampaign: Parse::nonEmptyString($data['utmCampaign'] ?? null),
            raw: $data,
        );
    }

    public function hasUtm(): bool
    {
        return $this->utmSource !== null || $this->utmMedium !== null || $this->utmCampaign !== null;
    }
}
