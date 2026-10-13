<?php

declare(strict_types=1);

namespace Digitonic\DrawHouse\Http\Requests;

use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Traits\Body\HasJsonBody;

/**
 * POST /refresh-access-token — no header auth; both tokens go in the body.
 * Returns a fresh TokenPair in `data`.
 */
final class RefreshAccessTokenRequest extends Request implements HasBody
{
    use HasJsonBody;

    protected Method $method = Method::POST;

    public function __construct(
        #[\SensitiveParameter] private readonly string $accessToken,
        #[\SensitiveParameter] private readonly string $refreshToken,
    ) {}

    public function resolveEndpoint(): string
    {
        return '/refresh-access-token';
    }

    /**
     * @return array<string, string>
     */
    protected function defaultBody(): array
    {
        return [
            'accessToken' => $this->accessToken,
            'refreshToken' => $this->refreshToken,
        ];
    }
}
