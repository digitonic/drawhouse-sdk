<?php

declare(strict_types=1);

namespace Digitonic\DrawHouse\DTO\Tickets;

use Carbon\CarbonImmutable;
use Digitonic\DrawHouse\Enums\TicketPaymentStatus;
use Digitonic\DrawHouse\Enums\TicketPaymentType;
use Digitonic\DrawHouse\Enums\TicketStatus;
use Digitonic\DrawHouse\Enums\TicketType;
use Digitonic\DrawHouse\Support\Parse;

/**
 * One row of the tickets feed, GET /v2/partners/tickets (Partner API v1.4):
 * a ticket bought by one of your players, in any draw, with its attribution
 * and raffle outcome.
 *
 * Every ticket bought in one basket shares `checkoutId`, which matches the
 * `ticket.purchased` webhook and the PURCHASE row on the money feed. Payment
 * outcome, refunds and draw results all move `updatedAt`.
 */
final readonly class PartnerTicket
{
    /**
     * @param  int|null  $id  Ticket row id, the paging key. Matches `id` on GET /v2/games/tickets.
     * @param  int|null  $ticketRef  The reference shown to the player on their ticket.
     * @param  int|null  $playerId  Matches `id` on the players feed.
     * @param  int|null  $editionId  The edition the player bought through, when the draw has editions.
     * @param  float|null  $price  Amount actually paid, GBP. Discounted batches cost less; granted tickets cost 0.
     * @param  int|null  $checkoutId  Shared by every ticket in one basket. Null for non-raffle and granted tickets.
     * @param  RaffleOutcome|null  $raffle  Null for non-raffle tickets.
     * @param  CarbonImmutable|null  $createdAt  Time of purchase.
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public ?int $id,
        public ?int $ticketRef,
        public ?int $playerId,
        public ?int $gameId,
        public ?int $roundId,
        public ?int $editionId,
        public ?TicketStatus $status,
        public ?TicketType $ticketType,
        public ?float $price,
        public ?TicketPaymentStatus $paymentStatus,
        public ?TicketPaymentType $paymentType,
        public ?int $checkoutId,
        public TicketAttribution $attribution,
        public ?RaffleOutcome $raffle,
        public ?CarbonImmutable $createdAt,
        public ?CarbonImmutable $updatedAt,
        public array $raw = [],
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: Parse::int($data['id'] ?? null),
            ticketRef: Parse::int($data['ticketRef'] ?? null),
            playerId: Parse::int($data['playerId'] ?? null),
            gameId: Parse::int($data['gameId'] ?? null),
            roundId: Parse::int($data['roundId'] ?? null),
            editionId: Parse::int($data['editionId'] ?? null),
            status: Parse::enum(TicketStatus::class, $data['status'] ?? null),
            ticketType: Parse::enum(TicketType::class, $data['ticketType'] ?? null),
            price: Parse::float($data['price'] ?? null),
            paymentStatus: Parse::enum(TicketPaymentStatus::class, $data['paymentStatus'] ?? null),
            paymentType: Parse::enum(TicketPaymentType::class, $data['paymentType'] ?? null),
            checkoutId: Parse::int($data['checkoutId'] ?? null),
            attribution: TicketAttribution::fromArray(is_array($data['attribution'] ?? null) ? $data['attribution'] : []),
            raffle: is_array($data['raffle'] ?? null) ? RaffleOutcome::fromArray($data['raffle']) : null,
            createdAt: Parse::dateTime($data['createdAt'] ?? null),
            updatedAt: Parse::dateTime($data['updatedAt'] ?? null),
            raw: $data,
        );
    }

    /**
     * A real purchase: payment succeeded. Refunded tickets keep SUCCEEDED,
     * so check `status` for REFUNDED as well when counting revenue.
     */
    public function isPurchased(): bool
    {
        return $this->paymentStatus === TicketPaymentStatus::SUCCEEDED;
    }

    public function isRefunded(): bool
    {
        return $this->status === TicketStatus::REFUNDED;
    }
}
