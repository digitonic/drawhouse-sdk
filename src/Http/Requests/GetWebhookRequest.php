<?php

declare(strict_types=1);

namespace Digitonic\DrawHouse\Http\Requests;

use Digitonic\DrawHouse\Http\Contracts\RequiresAccessToken;
use Saloon\Enums\Method;
use Saloon\Http\Request;

/**
 * GET /v2/partners/webhooks/{id} — one endpoint, without its secret. 404 when
 * it is not yours or has been deleted.
 */
final class GetWebhookRequest extends Request implements RequiresAccessToken
{
    protected Method $method = Method::GET;

    public function __construct(private readonly int $webhookId) {}

    public function resolveEndpoint(): string
    {
        return '/v2/partners/webhooks/'.$this->webhookId;
    }
}
