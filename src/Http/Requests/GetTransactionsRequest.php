<?php

declare(strict_types=1);

namespace Digitonic\DrawHouse\Http\Requests;

use Digitonic\DrawHouse\Http\Contracts\RequiresAccessToken;
use Digitonic\DrawHouse\Http\Requests\Concerns\SendsRepeatableQuery;
use Digitonic\DrawHouse\Support\TransactionsQuery;
use Saloon\Enums\Method;
use Saloon\Http\Request;

/**
 * GET /v2/partners/transactions, or /v2/partners/players/{id}/transactions
 * when a player is given — one page of the money feed (Partner API v1.4).
 *
 * The affiliate is taken from the token. The cursor is opaque: send it back
 * verbatim. A player outside your affiliate returns an empty page, not a 404.
 *
 * Rate guidance: daily, with `updatedAfter`.
 */
final class GetTransactionsRequest extends Request implements RequiresAccessToken
{
    use SendsRepeatableQuery;

    protected Method $method = Method::GET;

    /**
     * @param  int|null  $playerId  Limit to one player, through the per-player route.
     * @param  string|null  $after  The previous page's `nextCursor`, verbatim. Omit for the first page.
     */
    public function __construct(
        private readonly TransactionsQuery $filters,
        private readonly ?int $playerId = null,
        private readonly ?string $after = null,
    ) {}

    public function resolveEndpoint(): string
    {
        return $this->playerId === null
            ? '/v2/partners/transactions'
            : '/v2/partners/players/'.$this->playerId.'/transactions';
    }

    /**
     * @return array<string, string|int|list<string|int>>
     */
    protected function defaultQuery(): array
    {
        $query = $this->filters->toQuery(forOnePlayer: $this->playerId !== null);

        if ($this->after !== null) {
            $query['after'] = $this->after;
        }

        return $query;
    }
}
