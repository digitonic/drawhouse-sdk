<?php

declare(strict_types=1);

namespace Digitonic\DrawHouse\DTO\Players;

use Carbon\CarbonImmutable;
use Digitonic\DrawHouse\Support\Parse;

/**
 * One channel's marketing consent: `{ granted, recordedAt }`.
 */
final readonly class Consent
{
    /**
     * @param  CarbonImmutable|null  $recordedAt  Always null for partnerOffers; that flag has no timestamp.
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public ?bool $granted,
        public ?CarbonImmutable $recordedAt,
        public array $raw = [],
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            granted: Parse::bool($data['granted'] ?? null),
            recordedAt: Parse::dateTime($data['recordedAt'] ?? null),
            raw: $data,
        );
    }

    public function isGranted(): bool
    {
        return $this->granted === true;
    }
}
