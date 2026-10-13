<?php

declare(strict_types=1);

namespace Digitonic\DrawHouse\DTO;

use Carbon\CarbonImmutable;
use Digitonic\DrawHouse\Enums\RoundState;
use Digitonic\DrawHouse\Support\Parse;

/**
 * The earliest round transition that has not yet run. Omitted from a round
 * when nothing is pending.
 */
final readonly class NextTransition
{
    /**
     * @param  string|null  $toState  Target state, verbatim.
     * @param  RoundState|null  $state  Target state as an enum, null if unrecognised.
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public ?string $toState,
        public ?RoundState $state,
        public ?CarbonImmutable $scheduledAt,
        public array $raw = [],
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            toState: Parse::string($data['toState'] ?? null),
            state: Parse::enum(RoundState::class, $data['toState'] ?? null),
            scheduledAt: Parse::dateTime($data['scheduledAt'] ?? null),
            raw: $data,
        );
    }
}
