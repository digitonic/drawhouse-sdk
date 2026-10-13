<?php

declare(strict_types=1);

namespace Digitonic\DrawHouse\DTO;

use Carbon\CarbonImmutable;
use Digitonic\DrawHouse\Support\Parse;

/**
 * Scheduled transition times for a round. Each entry is omitted when no
 * such transition is scheduled. If a transition was scheduled more than once
 * the latest time is reported.
 */
final readonly class RoundSchedule
{
    /**
     * @param  CarbonImmutable|null  $opensAt  Transition to OPEN — entries open.
     * @param  CarbonImmutable|null  $drawAt  Transition to RESULTED — entries close and the draw runs.
     *                                        Typically 15 minutes before fields.scheduledDrawTime.
     * @param  CarbonImmutable|null  $liveDrawAt  Transition to LIVE_DRAW. Only when a live draw is armed.
     * @param  CarbonImmutable|null  $completesAt  Transition to COMPLETED.
     * @param  CarbonImmutable|null  $cancelledAt  Transition to CANCELLED. Only when the round was cancelled.
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public ?CarbonImmutable $opensAt,
        public ?CarbonImmutable $drawAt,
        public ?CarbonImmutable $liveDrawAt,
        public ?CarbonImmutable $completesAt,
        public ?CarbonImmutable $cancelledAt,
        public array $raw = [],
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            opensAt: Parse::dateTime($data['opensAt'] ?? null),
            drawAt: Parse::dateTime($data['drawAt'] ?? null),
            liveDrawAt: Parse::dateTime($data['liveDrawAt'] ?? null),
            completesAt: Parse::dateTime($data['completesAt'] ?? null),
            cancelledAt: Parse::dateTime($data['cancelledAt'] ?? null),
            raw: $data,
        );
    }
}
