<?php

declare(strict_types=1);

namespace Digitonic\DrawHouse\Enums;

/**
 * One value of `changes` on a `player.updated` event: which group of inputs
 * moved in the write that produced it. One event can carry several.
 */
enum PlayerChange: string
{
    /**
     * Account status, GAMSTOP flag, KYC result, self-exclusion or a time-out.
     * Compare `accountStatus` and `suppression`.
     */
    case STATUS = 'status';

    /**
     * Email or SMS marketing consent, partner-offers consent, or an email
     * suppression (bounce, complaint, unsubscribe). Compare `marketable` and
     * `consents`.
     */
    case CONSENT = 'consent';

    /**
     * Email address, name, phone, date of birth or postal address. The event
     * carries no PII, so re-fetch the player for the new values.
     */
    case PROFILE = 'profile';
}
