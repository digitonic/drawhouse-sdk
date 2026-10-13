<?php

declare(strict_types=1);

namespace Digitonic\DrawHouse\Http\Requests;

use Digitonic\DrawHouse\Http\Contracts\RequiresAccessToken;
use Saloon\Enums\Method;
use Saloon\Http\Request;

/**
 * GET /v2/players/balances
 *
 * Rate guidance: no more than once every 15 minutes.
 */
final class GetBalancesRequest extends Request implements RequiresAccessToken
{
    protected Method $method = Method::GET;

    public function resolveEndpoint(): string
    {
        return '/v2/players/balances';
    }
}
