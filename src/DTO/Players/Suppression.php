<?php

declare(strict_types=1);

namespace Digitonic\DrawHouse\DTO\Players;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Digitonic\DrawHouse\Support\Parse;

/**
 * `suppression` on a players-feed row: the inputs behind `accountStatus`
 * and `marketable`.
 *
 * `timedOutUntil` is the one input that changes on its own: TIMED_OUT is
 * computed at read time from it and clears without any event. Evaluate it
 * yourself with isTimedOutAt() rather than trusting a cached status.
 */
final readonly class Suppression
{
    /**
     * @param  string|null  $kycStatus  e.g. VERIFIED. `marketable` requires VERIFIED.
     * @param  bool|null  $emailSuppressed  On DrawHouse's bounce, complaint or unsubscribe list.
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public ?bool $selfExcluded,
        public ?CarbonImmutable $selfExclusionUntil,
        public ?bool $selfExclusionForever,
        public ?bool $gamstop,
        public ?CarbonImmutable $timedOutUntil,
        public ?string $kycStatus,
        public ?bool $emailSuppressed,
        public array $raw = [],
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            selfExcluded: Parse::bool($data['selfExcluded'] ?? null),
            selfExclusionUntil: Parse::dateTime($data['selfExclusionUntil'] ?? null),
            selfExclusionForever: Parse::bool($data['selfExclusionForever'] ?? null),
            gamstop: Parse::bool($data['gamstop'] ?? null),
            timedOutUntil: Parse::dateTime($data['timedOutUntil'] ?? null),
            kycStatus: Parse::nonEmptyString($data['kycStatus'] ?? null),
            emailSuppressed: Parse::bool($data['emailSuppressed'] ?? null),
            raw: $data,
        );
    }

    /**
     * Whether the player's time-out is still running at `$at`.
     */
    public function isTimedOutAt(CarbonInterface $at): bool
    {
        return $this->timedOutUntil !== null && $this->timedOutUntil->greaterThan($at);
    }

    public function isKycVerified(): bool
    {
        return strtoupper((string) $this->kycStatus) === 'VERIFIED';
    }
}
