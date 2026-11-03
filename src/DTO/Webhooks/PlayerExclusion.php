<?php

declare(strict_types=1);

namespace Digitonic\DrawHouse\DTO\Webhooks;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Digitonic\DrawHouse\Enums\ExclusionKind;
use Digitonic\DrawHouse\Support\Parse;

/**
 * `exclusion` on a player event: the self-exclusion or time-out in force
 * after the write, with its end. Derived from the same inputs as
 * `suppression` (`selfExcluded`, `selfExclusionUntil`, `timedOutUntil`), so
 * a rule needs one field for the kind and one for the end.
 *
 * A time-out ends without any event: evaluate it with isInForceAt() rather
 * than trusting `active`.
 */
final readonly class PlayerExclusion
{
    /**
     * @param  bool|null  $active  A self-exclusion or time-out is in force.
     * @param  ExclusionKind|null  $kind  Null when nothing is in force.
     * @param  CarbonImmutable|null  $until  When it ends. Null when nothing is in force or the self-exclusion is permanent.
     * @param  bool|null  $forever  Permanent self-exclusion; `until` is then null.
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public ?bool $active,
        public ?ExclusionKind $kind,
        public ?CarbonImmutable $until,
        public ?bool $forever,
        public array $raw = [],
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            active: Parse::bool($data['active'] ?? null),
            kind: Parse::enum(ExclusionKind::class, $data['kind'] ?? null),
            until: Parse::dateTime($data['until'] ?? null),
            forever: Parse::bool($data['forever'] ?? null),
            raw: $data,
        );
    }

    /**
     * Whether the restriction is still in force at `$at`: a permanent one
     * always is, a timed one until `until` passes.
     */
    public function isInForceAt(CarbonInterface $at): bool
    {
        if ($this->active !== true) {
            return false;
        }

        if ($this->forever === true || $this->until === null) {
            return true;
        }

        return $this->until->greaterThan($at);
    }

    public function isSelfExclusion(): bool
    {
        return $this->kind === ExclusionKind::SELF_EXCLUSION;
    }

    public function isTimeout(): bool
    {
        return $this->kind === ExclusionKind::TIMEOUT;
    }
}
