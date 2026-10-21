<?php

declare(strict_types=1);

namespace Digitonic\DrawHouse\DTO\Tickets;

use Digitonic\DrawHouse\Support\Parse;

/**
 * The UTM triple recorded when a ticket or checkout was bought: `attribution`
 * on the tickets feed and on `ticket.purchased`. DrawHouse send empty strings
 * when the purchase did not follow a campaign link; they are null here.
 */
final readonly class TicketAttribution
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
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
