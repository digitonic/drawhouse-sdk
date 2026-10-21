<?php

declare(strict_types=1);

namespace Digitonic\DrawHouse\DTO\Webhooks;

use Digitonic\DrawHouse\Enums\PrizeCategory;
use Digitonic\DrawHouse\Enums\PrizeFulfilment;
use Digitonic\DrawHouse\Support\Parse;

/**
 * `data` of a `draw.won` event (Partner API v1.4): a draw prize assigned to
 * one of your players' tickets. One event per prize assignment
 * (`drawPrizeId`); the resource is the raffle ticket.
 */
final readonly class DrawWonData
{
    /**
     * @param  int|null  $editionId  The edition the winning ticket was bought through; null for the default site.
     * @param  int|null  $ticketId  The ticket row id, as the tickets feed reports it.
     * @param  int|null  $raffleTicketId  The raffle number row id (the resource id).
     * @param  int|null  $drawPrizeId  One per prize assignment. Deduplicate on this.
     * @param  int|null  $prizePoolId  Matches `prizes[].prizePoolId` on the live-draw feed.
     * @param  float|null  $prizeAmount  Cash or site-credit value, or the declared value of a physical or voucher prize. Null when the prize has none.
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public ?int $playerId,
        public ?int $gameId,
        public ?string $gameName,
        public ?int $roundId,
        public ?int $editionId,
        public ?int $ticketId,
        public ?int $raffleTicketId,
        public ?int $ticketNumber,
        public ?int $drawPrizeId,
        public ?int $prizePoolId,
        public ?string $prizeName,
        public ?float $prizeAmount,
        public ?PrizeCategory $prizeCategory,
        public ?PrizeFulfilment $fulfilment,
        public array $raw = [],
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            playerId: Parse::int($data['playerId'] ?? null),
            gameId: Parse::int($data['gameId'] ?? null),
            gameName: Parse::nonEmptyString($data['gameName'] ?? null),
            roundId: Parse::int($data['roundId'] ?? null),
            editionId: Parse::int($data['editionId'] ?? null),
            ticketId: Parse::int($data['ticketId'] ?? null),
            raffleTicketId: Parse::int($data['raffleTicketId'] ?? null),
            ticketNumber: Parse::int($data['ticketNumber'] ?? null),
            drawPrizeId: Parse::int($data['drawPrizeId'] ?? null),
            prizePoolId: Parse::int($data['prizePoolId'] ?? null),
            prizeName: Parse::nonEmptyString($data['prizeName'] ?? null),
            prizeAmount: Parse::float($data['prizeAmount'] ?? null),
            prizeCategory: Parse::enum(PrizeCategory::class, $data['prizeCategory'] ?? null),
            fulfilment: Parse::enum(PrizeFulfilment::class, $data['fulfilment'] ?? null),
            raw: $data,
        );
    }
}
