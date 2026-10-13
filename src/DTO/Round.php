<?php

declare(strict_types=1);

namespace Digitonic\DrawHouse\DTO;

use Digitonic\DrawHouse\Enums\RoundState;
use Digitonic\DrawHouse\Support\Parse;

/**
 * A round of a game. `ticketPrice` is a STRING on the wire ("0.20") and is
 * normalised to float. `nextTransition` and `prizes` are omitted when absent;
 * `schedule` is always present.
 */
final readonly class Round
{
    /**
     * @param  int|null  $roundNumber  1-based.
     * @param  float|null  $ticketPrice  Regular ticket price. Omitted when not set.
     * @param  list<Prize>  $prizes
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public ?int $id,
        public ?int $roundNumber,
        public ?RoundState $state,
        public ?float $ticketPrice,
        public RoundSchedule $schedule,
        public ?NextTransition $nextTransition,
        public array $prizes,
        public array $raw = [],
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: Parse::int($data['id'] ?? null),
            roundNumber: Parse::int($data['roundNumber'] ?? null),
            state: Parse::enum(RoundState::class, $data['state'] ?? null),
            ticketPrice: Parse::float($data['ticketPrice'] ?? null),
            schedule: RoundSchedule::fromArray(is_array($data['schedule'] ?? null) ? $data['schedule'] : []),
            nextTransition: is_array($data['nextTransition'] ?? null) ? NextTransition::fromArray($data['nextTransition']) : null,
            prizes: array_map(Prize::fromArray(...), Parse::items($data['prizes'] ?? [])),
            raw: $data,
        );
    }

    /**
     * OPEN or LIVE_DRAW — the window in which ticket polling is worthwhile.
     */
    public function isActive(): bool
    {
        return $this->state?->isActive() ?? false;
    }
}
