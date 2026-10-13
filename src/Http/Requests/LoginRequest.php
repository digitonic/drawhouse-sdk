<?php

declare(strict_types=1);

namespace Digitonic\DrawHouse\Http\Requests;

use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Traits\Body\HasJsonBody;

/**
 * POST /login — no auth. Returns a TokenPair in `data`.
 */
final class LoginRequest extends Request implements HasBody
{
    use HasJsonBody;

    protected Method $method = Method::POST;

    public function __construct(
        private readonly string $email,
        #[\SensitiveParameter] private readonly string $password,
    ) {}

    public function resolveEndpoint(): string
    {
        return '/login';
    }

    /**
     * @return array<string, string>
     */
    protected function defaultBody(): array
    {
        return [
            'email' => $this->email,
            'password' => $this->password,
        ];
    }
}
