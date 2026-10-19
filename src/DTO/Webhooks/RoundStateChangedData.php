<?php

declare(strict_types=1);

namespace Digitonic\DrawHouse\DTO\Webhooks;

use Digitonic\DrawHouse\Enums\RoundState;
use Digitonic\DrawHouse\Support\Parse;

/**
 * `data` of a `round.state_changed` event.
 *
 * Sent once per transition into OPEN, RESULTED, LIVE_DRAW, COMPLETED or
 * CANCELLED; setup-phase moves (SETUP, READY, VOID) are never sent, so the
 * first event for a round is READY → OPEN. A typical round goes OPEN,
 * RESULTED, optionally LIVE_DRAW and back to RESULTED while the draw is
 * revealed live, then COMPLETED. A round can go OPEN → CANCELLED instead.
 *
 * The payload carries no player data. When `toState` is RESULTED, pull
 * winners and tickets from the draw endpoints.
 */
final readonly class RoundStateChangedData
{
    /**
     * @param  int|null  $gameId  Matches `id` on the games catalogue and `gameId` on the live feed.
     * @param  RoundState|null  $fromState  The state the round left.
     * @param  RoundState|null  $toState  OPEN, RESULTED, LIVE_DRAW, COMPLETED or CANCELLED.
     * @param  list<WebhookEdition>  $editions  Your active editions of the game; empty when you have none.
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public ?int $gameId,
        public ?string $gameName,
        public ?int $roundId,
        public ?int $roundNumber,
        public ?RoundState $fromState,
        public ?RoundState $toState,
        public array $editions,
        public array $raw = [],
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            gameId: Parse::int($data['gameId'] ?? null),
            gameName: Parse::nonEmptyString($data['gameName'] ?? null),
            roundId: Parse::int($data['roundId'] ?? null),
            roundNumber: Parse::int($data['roundNumber'] ?? null),
            fromState: Parse::enum(RoundState::class, $data['fromState'] ?? null),
            toState: Parse::enum(RoundState::class, $data['toState'] ?? null),
            editions: array_map(WebhookEdition::fromArray(...), Parse::items($data['editions'] ?? [])),
            raw: $data,
        );
    }

    /**
     * Your default edition, else your first one.
     */
    public function defaultEdition(): ?WebhookEdition
    {
        foreach ($this->editions as $edition) {
            if ($edition->isDefault === true) {
                return $edition;
            }
        }

        return $this->editions[0] ?? null;
    }

    /**
     * READY → OPEN: tickets are on sale.
     */
    public function isOpened(): bool
    {
        return $this->toState === RoundState::OPEN;
    }

    /**
     * Winners are decided. Also sent again when a live draw finishes revealing
     * (LIVE_DRAW → RESULTED); check `fromState` to tell the two apart.
     */
    public function isResulted(): bool
    {
        return $this->toState === RoundState::RESULTED;
    }
}
