<?php

declare(strict_types=1);

namespace Digitonic\DrawHouse\Http\Requests;

use Digitonic\DrawHouse\Http\Contracts\RequiresAccessToken;
use Saloon\Enums\Method;
use Saloon\Http\Request;

/**
 * POST /v2/partners/webhooks/{id}/test — queue a `webhook.ping` to this
 * endpoint only, signed and delivered like a real event. Answers 202 with the
 * queued event.
 */
final class TestWebhookRequest extends Request implements RequiresAccessToken
{
    protected Method $method = Method::POST;

    public function __construct(private readonly int $webhookId) {}

    public function resolveEndpoint(): string
    {
        return '/v2/partners/webhooks/'.$this->webhookId.'/test';
    }
}
