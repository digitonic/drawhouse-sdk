<?php

declare(strict_types=1);

namespace Digitonic\DrawHouse\DTO\LiveDraws;

use Carbon\CarbonImmutable;
use Digitonic\DrawHouse\Support\Parse;

/**
 * `liveDraw` on a live draw: ONLY AFTER the round is resulted (null before).
 * `armed` says whether a live (streamed) draw is set up; `drawTime` is when
 * it runs; `revealed` / `revealedAt` whether and when the result was shown.
 */
final readonly class LiveDrawStatus
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public ?string $state,
        public ?bool $armed,
        public ?CarbonImmutable $drawTime,
        public ?bool $revealed,
        public ?CarbonImmutable $revealedAt,
        public array $raw = [],
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            state: Parse::nonEmptyString($data['state'] ?? null),
            armed: Parse::bool($data['armed'] ?? null),
            drawTime: Parse::dateTime($data['drawTime'] ?? null),
            revealed: Parse::bool($data['revealed'] ?? null),
            revealedAt: Parse::dateTime($data['revealedAt'] ?? null),
            raw: $data,
        );
    }
}
