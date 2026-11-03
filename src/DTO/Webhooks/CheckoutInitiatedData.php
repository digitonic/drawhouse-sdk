<?php

declare(strict_types=1);

namespace Digitonic\DrawHouse\DTO\Webhooks;

use Digitonic\DrawHouse\Support\Parse;

/**
 * `data` of a `checkout.initiated` event: a player opened checkout. The
 * resource is the checkout intent, and `occurredAt` is when it opened.
 *
 * There is no `checkout.abandoned` event, no UTM triple, and nothing links
 * `intentId` to the `checkoutId` on a later `ticket.purchased`: a player can
 * open checkout several times for one purchase. Match a purchase on the
 * player and round instead.
 */
final readonly class CheckoutInitiatedData
{
    /**
     * @param  string|null  $intentId  The browser's id for this checkout opening; also the resource id and the pixel's InitiateCheckout event id (`checkout:<intentId>`).
     * @param  int|null  $editionId  The edition the player is entering through; null on the default site.
     * @param  int|null  $quantity  Tickets selected when checkout opened.
     * @param  float|null  $amount  Price of those tickets when checkout opened, GBP.
     * @param  bool|null  $payStepShown  False when the wallet already covered the entry and no card form was shown.
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public ?int $playerId,
        public ?string $intentId,
        public ?int $gameId,
        public ?string $gameName,
        public ?int $roundId,
        public ?int $editionId,
        public ?int $quantity,
        public ?float $amount,
        public ?string $currency,
        public ?bool $payStepShown,
        public array $raw = [],
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            playerId: Parse::int($data['playerId'] ?? null),
            intentId: Parse::nonEmptyString($data['intentId'] ?? null),
            gameId: Parse::int($data['gameId'] ?? null),
            gameName: Parse::nonEmptyString($data['gameName'] ?? null),
            roundId: Parse::int($data['roundId'] ?? null),
            editionId: Parse::int($data['editionId'] ?? null),
            quantity: Parse::int($data['quantity'] ?? null),
            amount: Parse::float($data['amount'] ?? null),
            currency: Parse::nonEmptyString($data['currency'] ?? null),
            payStepShown: Parse::bool($data['payStepShown'] ?? null),
            raw: $data,
        );
    }
}
