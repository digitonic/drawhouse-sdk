<?php

declare(strict_types=1);

namespace Digitonic\DrawHouse\Http\Requests;

use Digitonic\DrawHouse\Http\Contracts\RequiresAccessToken;
use Saloon\Enums\Method;
use Saloon\Http\Request;

/**
 * GET /v2/affiliates/reports/{dailyReportId}/utms
 *
 * Rate guidance: daily, for the previous day.
 */
final class GetReportUtmsRequest extends Request implements RequiresAccessToken
{
    protected Method $method = Method::GET;

    public function __construct(private readonly int $dailyReportId) {}

    public function resolveEndpoint(): string
    {
        return '/v2/affiliates/reports/'.$this->dailyReportId.'/utms';
    }
}
