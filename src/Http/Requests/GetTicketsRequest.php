<?php

declare(strict_types=1);

namespace Digitonic\DrawHouse\Http\Requests;

use Digitonic\DrawHouse\Http\Contracts\RequiresAccessToken;
use Saloon\Enums\Method;
use Saloon\Http\Request;

/**
 * GET /v2/games/tickets?gameId=&limit=&offset=
 *
 * Offset-paged since Partner API v1.4: `limit` defaults to 1000 and is
 * clamped to 5000; `total` in the response counts every matching ticket.
 * Without paging only the first 1000 come back.
 *
 * Rate guidance: no more than once every 5 minutes.
 */
final class GetTicketsRequest extends Request implements RequiresAccessToken
{
    public const DEFAULT_LIMIT = 1000;

    public const MAX_LIMIT = 5000;

    protected Method $method = Method::GET;

    public function __construct(
        private readonly int $gameId,
        private readonly int $limit = self::DEFAULT_LIMIT,
        private readonly int $offset = 0,
    ) {}

    public function resolveEndpoint(): string
    {
        return '/v2/games/tickets';
    }

    /**
     * @return array<string, int>
     */
    protected function defaultQuery(): array
    {
        return ['gameId' => $this->gameId, 'limit' => $this->limit, 'offset' => $this->offset];
    }
}
