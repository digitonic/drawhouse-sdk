<?php

declare(strict_types=1);

namespace Digitonic\DrawHouse\Enums;

/**
 * `accountStatus` on the players feed. "First matching row
 * wins", in this order; `statusChangedAt` moves whenever any input changes.
 *
 * TIMED_OUT is special: it is COMPUTED AT READ TIME from
 * `suppression.timedOutUntil`, so it clears without a status change event
 * and without `statusChangedAt` or `updatedAt` moving. A cached status of
 * TIMED_OUT (or ACTIVE, for a player whose time-out started after the pull)
 * cannot be trusted later; re-evaluate with PlayerDetail::isTimedOutAt() /
 * effectiveStatusAt() against `timedOutUntil` instead.
 */
enum AccountStatus: string
{
    /** Account erased. Cannot log in. */
    case DELETED = 'DELETED';

    /** Player closed the account, or an admin did. Cannot log in. */
    case DEACTIVATED = 'DEACTIVATED';

    /** On the GAMSTOP register. */
    case GAMSTOP_EXCLUDED = 'GAMSTOP_EXCLUDED';

    /** Self-excluded on DrawHouse. See suppression.selfExclusionUntil / selfExclusionForever. */
    case SELF_EXCLUDED = 'SELF_EXCLUDED';

    /** Identity verification failed. */
    case KYC_FAILED = 'KYC_FAILED';

    /** Took a break; suppression.timedOutUntil is when it ends. Computed at read time. */
    case TIMED_OUT = 'TIMED_OUT';

    /** None of the above. */
    case ACTIVE = 'ACTIVE';

    /**
     * True for every status that must suppress marketing on every channel.
     * Only ACTIVE is contactable, and even then only when `marketable` is true.
     */
    public function isSuppressed(): bool
    {
        return $this !== self::ACTIVE;
    }

    /**
     * True for statuses that are permanent until DrawHouse say otherwise
     * (as opposed to TIMED_OUT, which expires on its own).
     */
    public function canLogIn(): bool
    {
        return $this !== self::DELETED && $this !== self::DEACTIVATED;
    }
}
