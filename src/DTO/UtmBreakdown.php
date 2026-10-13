<?php

declare(strict_types=1);

namespace Digitonic\DrawHouse\DTO;

use Digitonic\DrawHouse\Support\Parse;

/**
 * One item of GET /v2/affiliates/reports/{dailyReportId}/utms.
 *
 * `source`, `medium` and `campaign` are the literal string "default" when
 * no UTM was present. Only these three UTMs exist; there is no utm_content.
 */
final readonly class UtmBreakdown
{
    public const DEFAULT = 'default';

    /**
     * @param  int|null  $count  Total tickets created against this UTM combination.
     * @param  int|null  $registered  Total players who registered against this UTM combination.
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public ?string $source,
        public ?string $medium,
        public ?string $campaign,
        public ?int $count,
        public ?int $registered,
        public array $raw = [],
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            source: Parse::string($data['source'] ?? null),
            medium: Parse::string($data['medium'] ?? null),
            campaign: Parse::string($data['campaign'] ?? null),
            count: Parse::int($data['count'] ?? null),
            registered: Parse::int($data['registered'] ?? null),
            raw: $data,
        );
    }

    /**
     * True when the row is the "no UTM present" bucket.
     */
    public function isUntracked(): bool
    {
        return $this->source === self::DEFAULT
            && $this->medium === self::DEFAULT
            && $this->campaign === self::DEFAULT;
    }
}
