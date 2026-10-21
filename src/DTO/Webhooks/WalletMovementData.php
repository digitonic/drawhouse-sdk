<?php

declare(strict_types=1);

namespace Digitonic\DrawHouse\DTO\Webhooks;

use Digitonic\DrawHouse\Enums\WalletMovementKind;
use Digitonic\DrawHouse\Support\Parse;

/**
 * `data` of a `wallet.credited` or `wallet.debited` event (Partner API
 * v1.4): one movement on a player's cash wallet. The event type gives the
 * direction; `amount` is always positive. The resource is the wallet.
 */
final readonly class WalletMovementData
{
    /**
     * @param  WalletMovementKind|null  $kind  Why the balance moved. Null for a kind this SDK does not know.
     * @param  float|null  $amount  GBP, always positive.
     * @param  float|null  $balanceAfter  The wallet balance after this movement.
     * @param  WalletMovementReference|null  $reference  Null when the writing operation has no ledger row of its own.
     * @param  int|null  $gameId  Present when the movement belongs to a game.
     * @param  int|null  $roundId  Present when the movement belongs to a round.
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public ?int $playerId,
        public ?int $walletId,
        public ?WalletMovementKind $kind,
        public ?float $amount,
        public ?float $balanceAfter,
        public ?string $currency,
        public ?WalletMovementReference $reference,
        public ?int $gameId,
        public ?int $roundId,
        public array $raw = [],
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            playerId: Parse::int($data['playerId'] ?? null),
            walletId: Parse::int($data['walletId'] ?? null),
            kind: Parse::enum(WalletMovementKind::class, $data['kind'] ?? null),
            amount: Parse::float($data['amount'] ?? null),
            balanceAfter: Parse::float($data['balanceAfter'] ?? null),
            currency: Parse::nonEmptyString($data['currency'] ?? null),
            reference: is_array($data['reference'] ?? null) ? WalletMovementReference::fromArray($data['reference']) : null,
            gameId: Parse::int($data['gameId'] ?? null),
            roundId: Parse::int($data['roundId'] ?? null),
            raw: $data,
        );
    }
}
