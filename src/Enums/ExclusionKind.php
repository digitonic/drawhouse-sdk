<?php

declare(strict_types=1);

namespace Digitonic\DrawHouse\Enums;

/**
 * `exclusion.kind` on a `player.registered` or `player.updated` event: the
 * player-initiated restriction in force. A self-exclusion takes precedence
 * over a concurrent time-out.
 */
enum ExclusionKind: string
{
    /** Self-excluded on DrawHouse, until `until` or for ever. */
    case SELF_EXCLUSION = 'SELF_EXCLUSION';

    /** Taking a break until `until`. Ends without any event. */
    case TIMEOUT = 'TIMEOUT';
}
