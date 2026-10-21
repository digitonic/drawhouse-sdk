<?php

declare(strict_types=1);

namespace Digitonic\DrawHouse\Enums;

/**
 * The day boundary for GET /v2/partners/metrics/daily.
 */
enum MetricsTimezone: string
{
    case EUROPE_LONDON = 'Europe/London';
    case UTC = 'UTC';
}
