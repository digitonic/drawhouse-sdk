<?php

declare(strict_types=1);

namespace Digitonic\DrawHouse\Http\Requests;

use Digitonic\DrawHouse\Http\Contracts\RequiresAccessToken;
use Saloon\Enums\Method;
use Saloon\Http\Request;

/**
 * GET /v2/partners/players/{id} — one player, with `lifetime` always
 * included. 404 when the player does not belong to you.
 */
final class GetPlayerRequest extends Request implements RequiresAccessToken
{
    protected Method $method = Method::GET;

    public function __construct(private readonly int $playerId) {}

    public function resolveEndpoint(): string
    {
        return '/v2/partners/players/'.$this->playerId;
    }
}
