<?php

declare(strict_types=1);

namespace Digitonic\DrawHouse\Http\Requests;

use Digitonic\DrawHouse\Http\Contracts\RequiresAccessToken;
use Saloon\Enums\Method;
use Saloon\Http\Request;

/**
 * GET /v2/partners/webhooks — your endpoints, oldest first, without their
 * secrets. The affiliate is taken from the token; the partner must be LIVE.
 */
final class ListWebhooksRequest extends Request implements RequiresAccessToken
{
    protected Method $method = Method::GET;

    public function resolveEndpoint(): string
    {
        return '/v2/partners/webhooks';
    }
}
