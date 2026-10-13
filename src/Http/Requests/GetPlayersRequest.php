<?php

declare(strict_types=1);

namespace Digitonic\DrawHouse\Http\Requests;

use Digitonic\DrawHouse\Http\Contracts\RequiresAccessToken;
use Digitonic\DrawHouse\Http\Requests\Concerns\SendsRepeatableQuery;
use Digitonic\DrawHouse\Support\PlayersQuery;
use Saloon\Enums\Method;
use Saloon\Http\Request;

/**
 * GET /v2/partners/players — one cursor page of the players feed.
 *
 * The affiliate is taken from the token; there is no affiliateId parameter.
 * The token must belong to a partner whose status is LIVE.
 *
 * Rate guidance: daily, with `updatedAfter`.
 */
final class GetPlayersRequest extends Request implements RequiresAccessToken
{
    use SendsRepeatableQuery;

    protected Method $method = Method::GET;

    /**
     * @param  string|null  $after  The previous page's `nextCursor`. Omit for the first page.
     */
    public function __construct(
        private readonly PlayersQuery $filters,
        private readonly ?string $after = null,
    ) {}

    public function resolveEndpoint(): string
    {
        return '/v2/partners/players';
    }

    /**
     * @return array<string, string|int|list<string>>
     */
    protected function defaultQuery(): array
    {
        $query = $this->filters->toQuery();

        if ($this->after !== null) {
            $query['after'] = $this->after;
        }

        return $query;
    }
}
