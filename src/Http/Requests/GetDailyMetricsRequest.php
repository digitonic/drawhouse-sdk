<?php

declare(strict_types=1);

namespace Digitonic\DrawHouse\Http\Requests;

use Digitonic\DrawHouse\Enums\MetricsGroupBy;
use Digitonic\DrawHouse\Enums\MetricsTimezone;
use Digitonic\DrawHouse\Http\Contracts\RequiresAccessToken;
use Saloon\Enums\Method;
use Saloon\Http\Request;

/**
 * GET /v2/partners/metrics/daily?from=&to=&timezone=&groupBy= (Partner API
 * v1.4). `from` and `to` are both inclusive, `YYYY-MM-DD`, at most 366 days
 * apart.
 *
 * Rate guidance: daily, for the previous day. Longer ranges are for
 * backfills.
 */
final class GetDailyMetricsRequest extends Request implements RequiresAccessToken
{
    public const MAX_DAYS = 366;

    protected Method $method = Method::GET;

    public function __construct(
        private readonly string $from,
        private readonly string $to,
        private readonly MetricsTimezone $timezone = MetricsTimezone::EUROPE_LONDON,
        private readonly MetricsGroupBy $groupBy = MetricsGroupBy::NONE,
    ) {}

    public function resolveEndpoint(): string
    {
        return '/v2/partners/metrics/daily';
    }

    /**
     * @return array<string, string>
     */
    protected function defaultQuery(): array
    {
        return [
            'from' => $this->from,
            'to' => $this->to,
            'timezone' => $this->timezone->value,
            'groupBy' => $this->groupBy->value,
        ];
    }
}
