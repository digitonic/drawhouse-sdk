<?php

declare(strict_types=1);

namespace Digitonic\DrawHouse\DTO\Webhooks;

use Digitonic\DrawHouse\Enums\InstantWinPrizeType;
use Digitonic\DrawHouse\Support\Parse;

/**
 * `data` of an `instant_win.revealed` event (Partner API v1.4): an instant
 * prize revealed on one ticket. The resource is the raffle ticket
 * (`raffleTicketId`); `checkoutId` names the `ticket.purchased` it belongs
 * to.
 */
final readonly class InstantWinData
{
    /**
     * @param  int|null  $ticketId  The ticket row id, as the tickets feed reports it.
     * @param  int|null  $raffleTicketId  The raffle number row id (the resource id). Deduplicate on this.
     * @param  float|null  $prizeAmount  GBP.
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public ?int $playerId,
        public ?int $ticketId,
        public ?int $raffleTicketId,
        public ?int $ticketNumber,
        public ?int $gameId,
        public ?int $roundId,
        public ?int $checkoutId,
        public ?int $editionId,
        public ?InstantWinPrizeType $prizeType,
        public ?string $prizeName,
        public ?float $prizeAmount,
        public array $raw = [],
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            playerId: Parse::int($data['playerId'] ?? null),
            ticketId: Parse::int($data['ticketId'] ?? null),
            raffleTicketId: Parse::int($data['raffleTicketId'] ?? null),
            ticketNumber: Parse::int($data['ticketNumber'] ?? null),
            gameId: Parse::int($data['gameId'] ?? null),
            roundId: Parse::int($data['roundId'] ?? null),
            checkoutId: Parse::int($data['checkoutId'] ?? null),
            editionId: Parse::int($data['editionId'] ?? null),
            prizeType: Parse::enum(InstantWinPrizeType::class, $data['prizeType'] ?? null),
            prizeName: Parse::nonEmptyString($data['prizeName'] ?? null),
            prizeAmount: Parse::float($data['prizeAmount'] ?? null),
            raw: $data,
        );
    }
}
