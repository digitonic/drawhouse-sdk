<?php

declare(strict_types=1);

namespace Digitonic\DrawHouse\DTO;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Digitonic\DrawHouse\DTO\Players\Activity;
use Digitonic\DrawHouse\DTO\Players\Attribution;
use Digitonic\DrawHouse\DTO\Players\Consents;
use Digitonic\DrawHouse\DTO\Players\Lifetime;
use Digitonic\DrawHouse\DTO\Players\Suppression;
use Digitonic\DrawHouse\DTO\Players\WalletBalances;
use Digitonic\DrawHouse\Enums\AccountStatus;
use Digitonic\DrawHouse\Support\Parse;

/**
 * One row of the players feed, GET /v2/partners/players (`data.items`) or
 * GET /v2/partners/players/{id}: attribution, account status,
 * activity and marketing consent together.
 *
 * Contains full PII. The SDK passes it through verbatim; the consuming
 * application is responsible for minimisation.
 *
 * `marketable` is DrawHouse's own rule (email consent AND KYC VERIFIED AND
 * not GAMSTOP / self-excluded / deactivated / deleted AND not on the bounce,
 * complaint or unsubscribe list). If it is false you must not contact the
 * player either.
 *
 * `accountStatus` TIMED_OUT is computed at read time from
 * `suppression.timedOutUntil` and clears without an event, so do not trust a
 * cached value: use isTimedOutAt() / effectiveStatusAt().
 */
final readonly class PlayerDetail
{
    /**
     * @param  int|null  $id  Matches `playerId` on draw tickets and `id` on the legacy report.
     * @param  CarbonImmutable|null  $updatedAt  Moves on any change to the player, their consents, status or purchases.
     * @param  CarbonImmutable|null  $statusChangedAt  Moves whenever any input to `accountStatus` changes.
     * @param  Lifetime|null  $lifetime  Only with `include=lifetime` on the list; always on /{id}.
     * @param  WalletBalances|null  $balances  The player's balances now (Partner API v1.4). Same gate as `lifetime`.
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public ?int $id,
        public ?string $email,
        public ?string $firstName,
        public ?string $lastName,
        public ?string $displayName,
        public ?string $phone,
        public ?CarbonImmutable $dateOfBirth,
        public ?string $gender,
        public ?CarbonImmutable $registeredAt,
        public ?CarbonImmutable $updatedAt,
        public ?AccountStatus $accountStatus,
        public ?CarbonImmutable $statusChangedAt,
        public ?bool $marketable,
        public Suppression $suppression,
        public Attribution $attribution,
        public Activity $activity,
        public Consents $consents,
        public ?Lifetime $lifetime,
        public ?WalletBalances $balances = null,
        public array $raw = [],
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $object = static fn (mixed $value): array => is_array($value) ? $value : [];

        return new self(
            id: Parse::int($data['id'] ?? null),
            email: Parse::string($data['email'] ?? null),
            firstName: Parse::string($data['firstName'] ?? null),
            lastName: Parse::string($data['lastName'] ?? null),
            displayName: Parse::string($data['displayName'] ?? null),
            phone: Parse::string($data['phone'] ?? null),
            dateOfBirth: Parse::date($data['dateOfBirth'] ?? null),
            gender: Parse::nonEmptyString($data['gender'] ?? null),
            registeredAt: Parse::dateTime($data['registeredAt'] ?? null),
            updatedAt: Parse::dateTime($data['updatedAt'] ?? null),
            accountStatus: Parse::enum(AccountStatus::class, $data['accountStatus'] ?? null),
            statusChangedAt: Parse::dateTime($data['statusChangedAt'] ?? null),
            marketable: Parse::bool($data['marketable'] ?? null),
            suppression: Suppression::fromArray($object($data['suppression'] ?? null)),
            attribution: Attribution::fromArray($object($data['attribution'] ?? null)),
            activity: Activity::fromArray($object($data['activity'] ?? null)),
            consents: Consents::fromArray($object($data['consents'] ?? null)),
            lifetime: is_array($data['lifetime'] ?? null) ? Lifetime::fromArray($data['lifetime']) : null,
            balances: is_array($data['balances'] ?? null) ? WalletBalances::fromArray($data['balances']) : null,
            raw: $data,
        );
    }

    /**
     * Whether the player is timed out at `$at`, evaluated from
     * `suppression.timedOutUntil` rather than the cached `accountStatus`.
     *
     * TIMED_OUT is computed by DrawHouse at read time and clears when
     * `timedOutUntil` passes, with no status change event and no movement of
     * `updatedAt`, so an incremental `updatedAfter` pull will never tell you
     * it ended. Likewise a time-out that started after your pull is invisible
     * until the next one. Always evaluate against the timestamp.
     */
    public function isTimedOutAt(CarbonInterface $at): bool
    {
        return $this->suppression->isTimedOutAt($at);
    }

    /**
     * `accountStatus` re-evaluated at `$at`: TIMED_OUT becomes ACTIVE once
     * `timedOutUntil` has passed, and ACTIVE becomes TIMED_OUT while it has
     * not. Every other status is returned unchanged, because the inputs to
     * those (deletion, GAMSTOP, self-exclusion, KYC) rank above TIMED_OUT in
     * DrawHouse's "first matching row wins" table and do not expire silently.
     */
    public function effectiveStatusAt(CarbonInterface $at): ?AccountStatus
    {
        return match ($this->accountStatus) {
            AccountStatus::TIMED_OUT => $this->isTimedOutAt($at) ? AccountStatus::TIMED_OUT : AccountStatus::ACTIVE,
            AccountStatus::ACTIVE => $this->isTimedOutAt($at) ? AccountStatus::TIMED_OUT : AccountStatus::ACTIVE,
            default => $this->accountStatus,
        };
    }

    /**
     * DrawHouse's `marketable` flag, additionally requiring that the player
     * is not timed out at `$at`. `marketable` alone does not exclude a
     * time-out, and the cached status may be stale.
     */
    public function isContactableAt(CarbonInterface $at): bool
    {
        return $this->marketable === true && ! $this->isTimedOutAt($at);
    }
}
