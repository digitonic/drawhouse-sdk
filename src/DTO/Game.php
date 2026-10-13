<?php

declare(strict_types=1);

namespace Digitonic\DrawHouse\DTO;

use Digitonic\DrawHouse\Enums\GameState;
use Digitonic\DrawHouse\Support\Parse;

/**
 * One item of GET /v2/partners/charity-raffles/games (`data.games`).
 *
 * `currentRoundId` and `editions` are omitted when absent. `fields` and
 * `rounds` are always present (rounds may be empty). Unknown extra fields
 * are preserved in `raw`.
 */
final readonly class Game
{
    /**
     * @param  string|null  $name  Base game name; your own wording is in editions[].name.
     * @param  int|null  $currentRoundId  Omitted when not set.
     * @param  bool|null  $isParticipating  True when you are attached (you have an edition).
     * @param  list<Edition>  $editions  Every active edition across all partners.
     * @param  list<Round>  $rounds  Ordered by roundNumber.
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public ?int $id,
        public ?string $name,
        public ?GameState $state,
        public ?int $currentRoundId,
        public ?bool $isParticipating,
        public GameFields $fields,
        public array $editions,
        public array $rounds,
        public array $raw = [],
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: Parse::int($data['id'] ?? null),
            name: Parse::string($data['name'] ?? null),
            state: Parse::enum(GameState::class, $data['state'] ?? null),
            currentRoundId: Parse::int($data['currentRoundId'] ?? null),
            isParticipating: Parse::bool($data['isParticipating'] ?? null),
            fields: GameFields::fromArray(is_array($data['fields'] ?? null) ? $data['fields'] : []),
            editions: array_map(Edition::fromArray(...), Parse::items($data['editions'] ?? [])),
            rounds: array_map(Round::fromArray(...), Parse::items($data['rounds'] ?? [])),
            raw: $data,
        );
    }

    public function currentRound(): ?Round
    {
        if ($this->currentRoundId === null) {
            return null;
        }

        foreach ($this->rounds as $round) {
            if ($round->id === $this->currentRoundId) {
                return $round;
            }
        }

        return null;
    }

    /**
     * The requesting partner's own edition, if any.
     */
    public function myEdition(): ?Edition
    {
        foreach ($this->editions as $edition) {
            if ($edition->isMine === true) {
                return $edition;
            }
        }

        return null;
    }
}
