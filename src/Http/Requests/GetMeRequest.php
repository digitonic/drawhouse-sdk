<?php

declare(strict_types=1);

namespace Digitonic\DrawHouse\Http\Requests;

use Digitonic\DrawHouse\Http\Contracts\RequiresAccessToken;
use Saloon\Enums\Method;
use Saloon\Http\Request;

/**
 * GET /v2/partners/me
 *
 * Rate guidance: call once and cache `affiliateId`.
 */
final class GetMeRequest extends Request implements RequiresAccessToken
{
    protected Method $method = Method::GET;

    public function resolveEndpoint(): string
    {
        return '/v2/partners/me';
    }
}
