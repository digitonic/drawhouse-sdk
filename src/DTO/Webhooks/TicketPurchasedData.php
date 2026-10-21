<?php

declare(strict_types=1);

namespace Digitonic\DrawHouse\DTO\Webhooks;

use Digitonic\DrawHouse\DTO\Tickets\TicketAttribution;
use Digitonic\DrawHouse\Support\Parse;

/**
 * `data` of a `ticket.purchased` event (Partner API v1.4): one completed
 * checkout, possibly many tickets. The resource is the checkout.
 *
 * `checkoutId` matches `checkoutId` on the tickets feed and the PURCHASE row
 * on the money feed, so either can reconcile a missed event. Each instant
 * win revealed at sale also arrives as its own `instant_win.revealed`.
 */
final readonly class TicketPurchasedData
{
    /**
     * @param  int|null  $editionId  The edition the player entered through; null when they bought on the default site.
     * @param  float|null  $amount  Full price of the basket, GBP.
     * @param  float|null  $cashAmount  The wallet-cash part.
     * @param  float|null  $siteCreditAmount  The site-credit part.
     * @param  list<int>  $ticketNumbers
     * @param  int|null  $instantWins  Prizes revealed at sale.
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public ?int $playerId,
        public ?int $checkoutId,
        public ?int $gameId,
        public ?string $gameName,
        public ?int $roundId,
        public ?int $editionId,
        public ?int $quantity,
        public ?float $amount,
        public ?float $cashAmount,
        public ?float $siteCreditAmount,
        public ?string $currency,
        public array $ticketNumbers,
        public ?int $instantWins,
        public TicketAttribution $attribution,
        public array $raw = [],
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $numbers = is_array($data['ticketNumbers'] ?? null) ? $data['ticketNumbers'] : [];

        return new self(
            playerId: Parse::int($data['playerId'] ?? null),
            checkoutId: Parse::int($data['checkoutId'] ?? null),
            gameId: Parse::int($data['gameId'] ?? null),
            gameName: Parse::nonEmptyString($data['gameName'] ?? null),
            roundId: Parse::int($data['roundId'] ?? null),
            editionId: Parse::int($data['editionId'] ?? null),
            quantity: Parse::int($data['quantity'] ?? null),
            amount: Parse::float($data['amount'] ?? null),
            cashAmount: Parse::float($data['cashAmount'] ?? null),
            siteCreditAmount: Parse::float($data['siteCreditAmount'] ?? null),
            currency: Parse::nonEmptyString($data['currency'] ?? null),
            ticketNumbers: array_values(array_filter(array_map(Parse::int(...), $numbers), static fn (?int $n): bool => $n !== null)),
            instantWins: Parse::int($data['instantWins'] ?? null),
            attribution: TicketAttribution::fromArray(is_array($data['attribution'] ?? null) ? $data['attribution'] : []),
            raw: $data,
        );
    }
}
