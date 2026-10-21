<?php

declare(strict_types=1);

namespace Digitonic\DrawHouse\Enums;

/**
 * `groupBy` on GET /v2/partners/metrics/daily.
 */
enum MetricsGroupBy: string
{
    /** One row per day. */
    case NONE = 'none';

    /** Split each day by the players' registration UTM triple and acquisition channel. */
    case SOURCE = 'source';

    /** Split the ticket figures only, by the edition the tickets were bought through. */
    case EDITION = 'edition';
}
