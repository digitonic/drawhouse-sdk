<?php

declare(strict_types=1);

namespace Digitonic\DrawHouse\Http\Requests;

use Digitonic\DrawHouse\Http\Contracts\RequiresAccessToken;
use Saloon\Enums\Method;
use Saloon\Http\Request;

/**
 * DELETE /v2/partners/webhooks/{id} — remove an endpoint. Deliveries still
 * queued to it are marked DEAD, and the id is not reused.
 */
final class DeleteWebhookRequest extends Request implements RequiresAccessToken
{
    protected Method $method = Method::DELETE;

    public function __construct(private readonly int $webhookId) {}

    public function resolveEndpoint(): string
    {
        return '/v2/partners/webhooks/'.$this->webhookId;
    }
}
