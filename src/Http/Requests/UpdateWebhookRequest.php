<?php

declare(strict_types=1);

namespace Digitonic\DrawHouse\Http\Requests;

use Digitonic\DrawHouse\Http\Contracts\RequiresAccessToken;
use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Traits\Body\HasJsonBody;

/**
 * PATCH /v2/partners/webhooks/{id} — change `url`, `events`, `description`
 * or `active`. Only the fields being changed are sent: an empty body and any
 * unknown field are both 400.
 */
final class UpdateWebhookRequest extends Request implements HasBody, RequiresAccessToken
{
    use HasJsonBody;

    protected Method $method = Method::PATCH;

    /**
     * @param  array<string, mixed>  $changes  Any of `url`, `events`, `description`, `active`; at least one.
     */
    public function __construct(
        private readonly int $webhookId,
        private readonly array $changes,
    ) {}

    public function resolveEndpoint(): string
    {
        return '/v2/partners/webhooks/'.$this->webhookId;
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaultBody(): array
    {
        return $this->changes;
    }
}
