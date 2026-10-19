<?php

declare(strict_types=1);

namespace Digitonic\DrawHouse\Http\Requests;

use Digitonic\DrawHouse\Http\Contracts\RequiresAccessToken;
use Saloon\Enums\Method;
use Saloon\Http\Request;

/**
 * POST /v2/partners/webhooks/{id}/rotate-secret — replace the signing secret.
 * Answers with the endpoint and its new `secret`, shown this once. 503 means
 * secret storage is unavailable and the old secret is still in force.
 */
final class RotateWebhookSecretRequest extends Request implements RequiresAccessToken
{
    protected Method $method = Method::POST;

    public function __construct(private readonly int $webhookId) {}

    public function resolveEndpoint(): string
    {
        return '/v2/partners/webhooks/'.$this->webhookId.'/rotate-secret';
    }
}
