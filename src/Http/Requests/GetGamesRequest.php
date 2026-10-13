<?php

declare(strict_types=1);

namespace Digitonic\DrawHouse\Http\Requests;

use Digitonic\DrawHouse\Enums\GameState;
use Digitonic\DrawHouse\Enums\Participation;
use Digitonic\DrawHouse\Http\Requests\Concerns\SendsRepeatableQuery;
use Saloon\Enums\Method;
use Saloon\Http\Request;

/**
 * GET /v2/partners/charity-raffles/games?affiliateId=&participation=&state=&limit=&offset=
 *
 * UNAUTHENTICATED — identified by `affiliateId` only.
 *
 * `state` is a REPEATABLE parameter (`state=READY&state=INPROGRESS`); see
 * SendsRepeatableQuery.
 *
 * Paging is applied before the participation filter, so a short page does
 * not mean the last page; keep going until a page is empty.
 */
final class GetGamesRequest extends Request
{
    use SendsRepeatableQuery;

    protected Method $method = Method::GET;

    /**
     * @param  list<GameState>|null  $states  null = API defaults (READY, INPROGRESS, REPORTED, RESULTED, COMPLETED)
     */
    public function __construct(
        private readonly int $affiliateId,
        private readonly ?Participation $participation = null,
        private readonly ?array $states = null,
        private readonly int $limit = 50,
        private readonly int $offset = 0,
    ) {}

    public function resolveEndpoint(): string
    {
        return '/v2/partners/charity-raffles/games';
    }

    /**
     * @return array<string, string|int|list<string>>
     */
    protected function defaultQuery(): array
    {
        $query = [
            'affiliateId' => $this->affiliateId,
            'limit' => $this->limit,
            'offset' => $this->offset,
        ];

        if ($this->participation !== null) {
            $query['participation'] = $this->participation->value;
        }

        if ($this->states !== null && $this->states !== []) {
            $query['state'] = array_values(array_unique(
                array_map(static fn (GameState $s): string => $s->value, $this->states)
            ));
        }

        return $query;
    }
}
