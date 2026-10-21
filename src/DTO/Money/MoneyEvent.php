<?php

declare(strict_types=1);

namespace Digitonic\DrawHouse\DTO\Money;

use Carbon\CarbonImmutable;
use Digitonic\DrawHouse\Enums\MoneyKind;
use Digitonic\DrawHouse\Support\Parse;

/**
 * One row of the money feed, GET /v2/partners/transactions (Partner API
 * v1.4): a deposit, purchase, refund, prize payout, withdrawal or
 * site-credit movement by one of your players. Nothing is cumulative, so
 * sum whatever window you like.
 *
 * `status` changes over time (a DEPOSIT goes CREATED → PROCESSING → SUCCESS
 * or FAILED; a PURCHASE can go SUCCEEDED → REFUNDED) and each change moves
 * `updatedAt`. Count money only on settled rows: isSettled().
 */
final readonly class MoneyEvent
{
    /**
     * The statuses on which each kind's money is real. REFUND, PRIZE_PAYOUT
     * and the site-credit kinds are only ever sent once settled.
     */
    private const SETTLED = [
        'DEPOSIT' => ['SUCCESS'],
        'ADMIN_CREDIT' => ['SUCCESS'],
        'PURCHASE' => ['SUCCEEDED', 'REFUNDED'],
        'WITHDRAWAL' => ['ADMIN_APPROVED', 'EXTERNAL_ADMIN_APPROVED', 'MANUALLY_APPROVED', 'PROCESSING_PAYOUT'],
    ];

    /**
     * @param  string|null  $id  Stable and unique across the feed: `<kind>:<row id>`, e.g. `DEPOSIT:9912`.
     * @param  MoneyKind|null  $kind  Null for a kind this SDK does not know (`kindName` keeps it).
     * @param  float|null  $amount  GBP, always positive; `kind` gives the direction. A PURCHASE's is the basket total.
     * @param  string|null  $status  Vocabulary depends on `kind`; site-credit kinds carry the credit's source (PRIZE_AWARDED, PROMO, REFUND).
     * @param  string|null  $method  WALLET or SITE_CREDIT on prizes and refunds; SITE_CREDIT on the site-credit kinds.
     * @param  float|null  $cashAmount  PURCHASE only: the part paid from the cash wallet.
     * @param  float|null  $siteCreditAmount  PURCHASE only: the part paid with site credit.
     * @param  int|null  $checkoutId  Matches `checkoutId` on the tickets feed and on `ticket.purchased`.
     * @param  string|null  $reference  Payment transaction id, checkout reference, payout type or free text, by kind.
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public ?string $id,
        public ?MoneyKind $kind,
        public ?string $kindName,
        public ?int $playerId,
        public ?float $amount,
        public ?string $currency,
        public ?string $status,
        public ?string $method,
        public ?float $cashAmount,
        public ?float $siteCreditAmount,
        public ?int $gameId,
        public ?int $roundId,
        public ?int $checkoutId,
        public ?string $reference,
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
            id: Parse::nonEmptyString($data['id'] ?? null),
            kind: Parse::enum(MoneyKind::class, $data['kind'] ?? null),
            kindName: Parse::nonEmptyString($data['kind'] ?? null),
            playerId: Parse::int($data['playerId'] ?? null),
            amount: Parse::float($data['amount'] ?? null),
            currency: Parse::nonEmptyString($data['currency'] ?? null),
            status: Parse::nonEmptyString($data['status'] ?? null),
            method: Parse::nonEmptyString($data['method'] ?? null),
            cashAmount: Parse::float($data['cashAmount'] ?? null),
            siteCreditAmount: Parse::float($data['siteCreditAmount'] ?? null),
            gameId: Parse::int($data['gameId'] ?? null),
            roundId: Parse::int($data['roundId'] ?? null),
            checkoutId: Parse::int($data['checkoutId'] ?? null),
            reference: Parse::nonEmptyString($data['reference'] ?? null),
            createdAt: Parse::dateTime($data['createdAt'] ?? null),
            updatedAt: Parse::dateTime($data['updatedAt'] ?? null),
            raw: $data,
        );
    }

    /**
     * Whether the row's money is real: a successful deposit or admin credit,
     * a purchase that succeeded (refunded ones included), an approved or
     * paying-out withdrawal, or any refund, prize payout or site-credit row.
     */
    public function isSettled(): bool
    {
        if ($this->kind === null) {
            return false;
        }

        $settled = self::SETTLED[$this->kind->value] ?? null;

        return $settled === null || in_array(strtoupper((string) $this->status), $settled, true);
    }
}
