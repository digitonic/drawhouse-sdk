<?php

declare(strict_types=1);

namespace Digitonic\DrawHouse\Enums;

/**
 * `participation` filter on /v2/partners/charity-raffles/games.
 *
 * Applied AFTER paging, so a `joined` or `available` page can be shorter
 * than `limit` without being the last page.
 */
enum Participation: string
{
    /** Every game (the API default). */
    case ALL = 'all';

    /** Only games you are attached to (you have an edition). */
    case JOINED = 'joined';

    /** Only games you are not attached to. */
    case AVAILABLE = 'available';
}
