<?php

declare(strict_types=1);

namespace Digitonic\DrawHouse\Http\Requests;

use Digitonic\DrawHouse\Enums\WebhookEventType;
use Digitonic\DrawHouse\Http\Contracts\RequiresAccessToken;
use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Traits\Body\HasJsonBody;

/**
 * POST /v2/partners/webhooks — register an endpoint. Answers 201 with the
 * endpoint and its `secret`, the only time it is returned apart from a
 * rotation. Unknown body fields, a non-public or non-https URL, an unknown
 * event type and an 11th endpoint are all 400; 503 means secret storage is
 * unavailable and nothing was created.
 */
final class CreateWebhookRequest extends Request implements HasBody, RequiresAccessToken
{
    use HasJsonBody;

    protected Method $method = Method::POST;

    /**
     * @param  list<WebhookEventType>  $events  Empty = every type.
     */
    public function __construct(
        private readonly string $url,
        private readonly array $events = [],
        private readonly ?string $description = null,
        private readonly bool $active = true,
    ) {}

    public function resolveEndpoint(): string
    {
        return '/v2/partners/webhooks';
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaultBody(): array
    {
        $body = [
            'url' => $this->url,
            'events' => array_values(array_unique(array_map(static fn (WebhookEventType $type): string => $type->value, $this->events))),
            'active' => $this->active,
        ];

        if ($this->description !== null) {
            $body['description'] = $this->description;
        }

        return $body;
    }
}
