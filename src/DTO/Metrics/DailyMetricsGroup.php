<?php

declare(strict_types=1);

namespace Digitonic\DrawHouse\DTO\Metrics;

use Digitonic\DrawHouse\Enums\AcquisitionChannel;
use Digitonic\DrawHouse\Support\Parse;

/**
 * `group` on a daily metrics row: the registration source with
 * `groupBy=source`, or the edition with `groupBy=edition`.
 */
final readonly class DailyMetricsGroup
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public ?string $utmSource,
        public ?string $utmMedium,
        public ?string $utmCampaign,
        public ?AcquisitionChannel $acquisitionChannel,
        public ?int $editionId,
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
            acquisitionChannel: Parse::enum(AcquisitionChannel::class, $data['acquisitionChannel'] ?? null),
            editionId: Parse::int($data['editionId'] ?? null),
            raw: $data,
        );
    }
}
