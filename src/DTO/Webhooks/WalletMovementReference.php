<?php

declare(strict_types=1);

namespace Digitonic\DrawHouse\DTO\Webhooks;

use Digitonic\DrawHouse\Support\Parse;

/**
 * `reference` on a wallet movement: the ledger row behind it.
 */
final readonly class WalletMovementReference
{
    /**
     * @param  string|null  $type  deposit, payout, checkout, withdrawal or award.
     * @param  string|null  $id  The row's id, as a string (a checkout id is numeric; a deposit id is a UUID).
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public ?string $type,
        public ?string $id,
        public array $raw = [],
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            type: Parse::nonEmptyString($data['type'] ?? null),
            id: Parse::nonEmptyString($data['id'] ?? null),
            raw: $data,
        );
    }
}
