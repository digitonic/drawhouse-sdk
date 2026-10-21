<?php

declare(strict_types=1);

namespace Digitonic\DrawHouse\DTO\Tickets;

use Carbon\CarbonImmutable;
use Digitonic\DrawHouse\Support\Parse;

/**
 * `raffle` on a tickets-feed row: the raffle side of the ticket.
 *
 * Prize fields follow the public draw pages' reveal rule: until
 * `prizeRevealed` is true, `hasPrize` is false and the prize fields are
 * null, whatever the ticket holds. An instant win therefore appears here at
 * the moment the player reveals it.
 */
final readonly class RaffleOutcome
{
    /**
     * @param  int|null  $ticketNumber  The number drawn against.
     * @param  string|null  $status  AVAILABLE, RESERVED, SOLD or VOID.
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public ?int $ticketNumber,
        public ?string $status,
        public ?bool $hasPrize,
        public ?bool $prizeRevealed,
        public ?string $prizeType,
        public ?string $prizeName,
        public ?float $prizeAmount,
        public ?bool $prizeClaimed,
        public ?CarbonImmutable $revealedAt,
        public ?CarbonImmutable $claimedAt,
        public array $raw = [],
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            ticketNumber: Parse::int($data['ticketNumber'] ?? null),
            status: Parse::nonEmptyString($data['status'] ?? null),
            hasPrize: Parse::bool($data['hasPrize'] ?? null),
            prizeRevealed: Parse::bool($data['prizeRevealed'] ?? null),
            prizeType: Parse::nonEmptyString($data['prizeType'] ?? null),
            prizeName: Parse::nonEmptyString($data['prizeName'] ?? null),
            prizeAmount: Parse::float($data['prizeAmount'] ?? null),
            prizeClaimed: Parse::bool($data['prizeClaimed'] ?? null),
            revealedAt: Parse::dateTime($data['revealedAt'] ?? null),
            claimedAt: Parse::dateTime($data['claimedAt'] ?? null),
            raw: $data,
        );
    }

    public function hasWon(): bool
    {
        return $this->prizeRevealed === true && $this->hasPrize === true;
    }
}
