<?php

declare(strict_types=1);

namespace Digitonic\DrawHouse\Http\Requests;

use Saloon\Enums\Method;
use Saloon\Http\Request;

/**
 * GET /v2/partners/charity-raffles/live/{gameId}?affiliateId=&editionId=
 *
 * UNAUTHENTICATED. 404 when that game has no live draw for your affiliate.
 * Cached server-side for 15 seconds.
 */
final class GetLiveDrawRequest extends Request
{
    protected Method $method = Method::GET;

    public function __construct(
        private readonly int $gameId,
        private readonly int $affiliateId,
        private readonly ?int $editionId = null,
    ) {}

    public function resolveEndpoint(): string
    {
        return '/v2/partners/charity-raffles/live/'.$this->gameId;
    }

    /**
     * @return array<string, int>
     */
    protected function defaultQuery(): array
    {
        $query = ['affiliateId' => $this->affiliateId];

        if ($this->editionId !== null) {
            $query['editionId'] = $this->editionId;
        }

        return $query;
    }
}
