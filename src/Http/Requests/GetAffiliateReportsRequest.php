<?php

declare(strict_types=1);

namespace Digitonic\DrawHouse\Http\Requests;

use Digitonic\DrawHouse\Enums\ReportInterval;
use Digitonic\DrawHouse\Http\Contracts\RequiresAccessToken;
use Saloon\Enums\Method;
use Saloon\Http\Request;

/**
 * GET /v2/affiliates/reports?interval=days|weeks&start=&end=
 *
 * Rate guidance: daily, for the previous day. Counts are cumulative snapshots.
 */
final class GetAffiliateReportsRequest extends Request implements RequiresAccessToken
{
    protected Method $method = Method::GET;

    public function __construct(
        private readonly string $start,
        private readonly string $end,
        private readonly ReportInterval $interval = ReportInterval::DAYS,
    ) {}

    public function resolveEndpoint(): string
    {
        return '/v2/affiliates/reports';
    }

    /**
     * @return array<string, string>
     */
    protected function defaultQuery(): array
    {
        return [
            'interval' => $this->interval->value,
            'start' => $this->start,
            'end' => $this->end,
        ];
    }
}
