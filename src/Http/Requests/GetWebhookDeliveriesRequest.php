<?php

declare(strict_types=1);

namespace Digitonic\DrawHouse\Http\Requests;

use Carbon\CarbonInterface;
use Digitonic\DrawHouse\Enums\WebhookDeliveryStatus;
use Digitonic\DrawHouse\Http\Contracts\RequiresAccessToken;
use Digitonic\DrawHouse\Http\Requests\Concerns\SendsRepeatableQuery;
use Saloon\Enums\Method;
use Saloon\Http\Request;

/**
 * GET /v2/partners/webhooks/{id}/deliveries?status=&after=&limit=&createdAfter=
 *
 * One cursor page of the delivery log, oldest first. `status` is repeatable;
 * `limit` defaults to 100 and is clamped to 500.
 */
final class GetWebhookDeliveriesRequest extends Request implements RequiresAccessToken
{
    use SendsRepeatableQuery;

    public const DEFAULT_LIMIT = 100;

    public const MAX_LIMIT = 500;

    protected Method $method = Method::GET;

    /**
     * @param  list<WebhookDeliveryStatus>  $statuses
     * @param  string|null  $after  The previous page's `nextCursor`. Omit for the first page.
     */
    public function __construct(
        private readonly int $webhookId,
        private readonly array $statuses = [],
        private readonly ?CarbonInterface $createdAfter = null,
        private readonly int $limit = self::DEFAULT_LIMIT,
        private readonly ?string $after = null,
    ) {}

    public function resolveEndpoint(): string
    {
        return '/v2/partners/webhooks/'.$this->webhookId.'/deliveries';
    }

    /**
     * @return array<string, string|int|list<string>>
     */
    protected function defaultQuery(): array
    {
        $query = [];

        if ($this->statuses !== []) {
            $query['status'] = array_values(array_unique(
                array_map(static fn (WebhookDeliveryStatus $s): string => $s->value, $this->statuses)
            ));
        }

        if ($this->createdAfter !== null) {
            $query['createdAfter'] = $this->createdAfter->toImmutable()->utc()->toIso8601ZuluString();
        }

        $query['limit'] = $this->limit;

        if ($this->after !== null) {
            $query['after'] = $this->after;
        }

        return $query;
    }
}
