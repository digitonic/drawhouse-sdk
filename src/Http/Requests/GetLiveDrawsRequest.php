<?php

declare(strict_types=1);

namespace Digitonic\DrawHouse\Http\Requests;

use Digitonic\DrawHouse\Enums\RoundState;
use Digitonic\DrawHouse\Http\Requests\Concerns\SendsRepeatableQuery;
use Saloon\Enums\Method;
use Saloon\Http\Request;

/**
 * GET /v2/partners/charity-raffles/live?affiliateId=&editionId=&state=&limit=
 *
 * UNAUTHENTICATED — identified by `affiliateId`, which picks the edition
 * (branding, prize names, images, URL). Cached server-side for 15 seconds;
 * poll every 15–30 seconds. `state` is repeatable.
 */
final class GetLiveDrawsRequest extends Request
{
    use SendsRepeatableQuery;

    public const DEFAULT_LIMIT = 20;

    public const MAX_LIMIT = 100;

    protected Method $method = Method::GET;

    /**
     * @param  list<RoundState>|null  $states  null = all four (OPEN, LIVE_DRAW, RESULTED, COMPLETED).
     */
    public function __construct(
        private readonly int $affiliateId,
        private readonly ?int $editionId = null,
        private readonly ?array $states = null,
        private readonly int $limit = self::DEFAULT_LIMIT,
    ) {}

    public function resolveEndpoint(): string
    {
        return '/v2/partners/charity-raffles/live';
    }

    /**
     * @return array<string, string|int|list<string>>
     */
    protected function defaultQuery(): array
    {
        $query = ['affiliateId' => $this->affiliateId];

        if ($this->editionId !== null) {
            $query['editionId'] = $this->editionId;
        }

        if ($this->states !== null && $this->states !== []) {
            $query['state'] = array_values(array_unique(
                array_map(static fn (RoundState $s): string => $s->value, $this->states)
            ));
        }

        $query['limit'] = $this->limit;

        return $query;
    }
}
