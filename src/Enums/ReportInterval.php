<?php

declare(strict_types=1);

namespace Digitonic\DrawHouse\Enums;

/**
 * `interval` on GET /v2/affiliates/reports. `weeks` was added in v1.2.
 */
enum ReportInterval: string
{
    case DAYS = 'days';
    case WEEKS = 'weeks';
}
