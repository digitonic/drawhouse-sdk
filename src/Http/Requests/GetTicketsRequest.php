<?php

declare(strict_types=1);

namespace Digitonic\DrawHouse\Http\Requests;

use Digitonic\DrawHouse\Http\Contracts\RequiresAccessToken;
use Saloon\Enums\Method;
use Saloon\Http\Request;

/**
 * GET /v2/games/tickets?gameId=
 *
 * Rate guidance: no more than once every 5 minutes.
 */
final class GetTicketsRequest extends Request implements RequiresAccessToken
{
    protected Method $method = Method::GET;

    public function __construct(private readonly int $gameId) {}

    public function resolveEndpoint(): string
    {
        return '/v2/games/tickets';
    }

    /**
     * @return array<string, int>
     */
    protected function defaultQuery(): array
    {
        return ['gameId' => $this->gameId];
    }
}
