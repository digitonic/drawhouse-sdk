<?php

declare(strict_types=1);

namespace Digitonic\DrawHouse\DTO\Webhooks;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Digitonic\DrawHouse\DTO\Players\Consents;
use Digitonic\DrawHouse\DTO\Players\Suppression;
use Digitonic\DrawHouse\Enums\AccountStatus;
use Digitonic\DrawHouse\Enums\PlayerChange;
use Digitonic\DrawHouse\Support\Parse;

/**
 * `data` of a `player.registered` or `player.updated` event: the player's
 * CURRENT state after the change, derived by the same rule as the players
 * feed. It carries no PII; fetch names and contact details with
 * DrawHouse::player($data->playerId).
 *
 * For `status` and `consent` changes the event alone is enough to update a
 * CRM. A `profile` change needs the re-fetch. `changes` is empty on
 * `player.registered`.
 *
 * TIMED_OUT expires silently: there is no event when `timedOutUntil`
 * passes, so evaluate it with isTimedOutAt() / effectiveStatusAt() rather
 * than trusting a stored `accountStatus`.
 */
final readonly class PlayerData
{
    /**
     * @param  int|null  $playerId  Matches `id` on the players feed.
     * @param  bool|null  $marketable  Same rule as the players feed; false means do not contact on any channel.
     * @param  Consents  $consents  As on the players feed; `partnerOffers->recordedAt` is always null.
     * @param  list<PlayerChange>  $changes  Which groups of inputs moved. Empty on `player.registered`.
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public ?int $playerId,
        public ?CarbonImmutable $registeredAt,
        public ?AccountStatus $accountStatus,
        public ?CarbonImmutable $statusChangedAt,
        public ?bool $marketable,
        public Suppression $suppression,
        public Consents $consents,
        public array $changes = [],
        public array $raw = [],
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $object = static fn (mixed $value): array => is_array($value) ? $value : [];

        $changes = [];
        foreach (is_array($data['changes'] ?? null) ? $data['changes'] : [] as $value) {
            $change = Parse::enum(PlayerChange::class, $value);

            if ($change !== null && ! in_array($change, $changes, true)) {
                $changes[] = $change;
            }
        }

        return new self(
            playerId: Parse::int($data['playerId'] ?? null),
            registeredAt: Parse::dateTime($data['registeredAt'] ?? null),
            accountStatus: Parse::enum(AccountStatus::class, $data['accountStatus'] ?? null),
            statusChangedAt: Parse::dateTime($data['statusChangedAt'] ?? null),
            marketable: Parse::bool($data['marketable'] ?? null),
            suppression: Suppression::fromArray($object($data['suppression'] ?? null)),
            consents: Consents::fromArray($object($data['consents'] ?? null)),
            changes: $changes,
            raw: $data,
        );
    }

    public function hasChange(PlayerChange $change): bool
    {
        return in_array($change, $this->changes, true);
    }

    /**
     * A `profile` change: the new values are not in the event.
     */
    public function needsProfileRefetch(): bool
    {
        return $this->hasChange(PlayerChange::PROFILE);
    }

    /**
     * See PlayerDetail::isTimedOutAt().
     */
    public function isTimedOutAt(CarbonInterface $at): bool
    {
        return $this->suppression->isTimedOutAt($at);
    }

    /**
     * See PlayerDetail::effectiveStatusAt().
     */
    public function effectiveStatusAt(CarbonInterface $at): ?AccountStatus
    {
        return match ($this->accountStatus) {
            AccountStatus::TIMED_OUT, AccountStatus::ACTIVE => $this->isTimedOutAt($at) ? AccountStatus::TIMED_OUT : AccountStatus::ACTIVE,
            default => $this->accountStatus,
        };
    }

    /**
     * See PlayerDetail::isContactableAt().
     */
    public function isContactableAt(CarbonInterface $at): bool
    {
        return $this->marketable === true && ! $this->isTimedOutAt($at);
    }
}
