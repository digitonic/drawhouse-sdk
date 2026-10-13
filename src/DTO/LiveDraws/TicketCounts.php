<?php

declare(strict_types=1);

namespace Digitonic\DrawHouse\DTO\LiveDraws;

use Digitonic\DrawHouse\Support\Parse;

/**
 * `tickets` on a live draw. `price` is a STRING on the wire ("5.00") and is
 * kept as one so no precision is lost; use priceAmount() for a float. The
 * counts are integers, taken from the minted ticket set, so they are exact.
 * "Tickets left" on a countdown is `available`.
 */
final readonly class TicketCounts
{
    /**
     * @param  string|null  $price  Regular ticket price, verbatim.
     * @param  int|null  $max  Ticket cap for the game.
     * @param  int|null  $sold  Tickets sold so far.
     * @param  int|null  $available  Tickets left.
     * @param  int|null  $maxPerPlayer  Ticket cap per player.
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public ?string $price,
        public ?int $max,
        public ?int $sold,
        public ?int $available,
        public ?int $maxPerPlayer,
        public array $raw = [],
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            price: Parse::nonEmptyString($data['price'] ?? null),
            max: Parse::int($data['max'] ?? null),
            sold: Parse::int($data['sold'] ?? null),
            available: Parse::int($data['available'] ?? null),
            maxPerPlayer: Parse::int($data['maxPerPlayer'] ?? null),
            raw: $data,
        );
    }

    public function priceAmount(): ?float
    {
        return Parse::float($this->price);
    }

    /**
     * Share of the cap sold, 0.0–1.0. Null without a cap.
     */
    public function soldFraction(): ?float
    {
        if ($this->max === null || $this->max <= 0 || $this->sold === null) {
            return null;
        }

        return min(1.0, $this->sold / $this->max);
    }

    public function isSoldOut(): bool
    {
        return $this->available === 0;
    }
}
