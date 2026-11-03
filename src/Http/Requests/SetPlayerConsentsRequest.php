<?php

declare(strict_types=1);

namespace Digitonic\DrawHouse\Http\Requests;

use Digitonic\DrawHouse\Http\Contracts\RequiresAccessToken;
use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Traits\Body\HasJsonBody;

/**
 * PATCH /v2/partners/players/{id}/consents — write the marketing consents
 * the partner captured onto one of its players. Only the flags sent move:
 * an empty body and any unknown field are both 400. The response is the
 * player, as GET /v2/partners/players/{id} returns it.
 */
final class SetPlayerConsentsRequest extends Request implements HasBody, RequiresAccessToken
{
    use HasJsonBody;

    protected Method $method = Method::PATCH;

    /**
     * @param  array<string, bool>  $consents  Any of `email`, `sms`, `partnerOffers`; at least one.
     */
    public function __construct(
        private readonly int $playerId,
        private readonly array $consents,
    ) {}

    public function resolveEndpoint(): string
    {
        return '/v2/partners/players/'.$this->playerId.'/consents';
    }

    /**
     * @return array<string, bool>
     */
    protected function defaultBody(): array
    {
        return $this->consents;
    }
}
