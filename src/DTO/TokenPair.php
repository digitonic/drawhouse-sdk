<?php

declare(strict_types=1);

namespace Digitonic\DrawHouse\DTO;

use Carbon\CarbonImmutable;
use Digitonic\DrawHouse\Support\Parse;

/**
 * Access + refresh token.
 *
 * POST /login returns all four fields at the top level of the body.
 * POST /refresh-access-token returns only `data.newAccessToken` and
 * `data.newAccessTokenExpiry`; the refresh token is unchanged.
 */
final readonly class TokenPair
{
    public function __construct(
        public string $accessToken,
        public string $refreshToken,
        public ?CarbonImmutable $accessTokenExpiry = null,
        public ?CarbonImmutable $refreshTokenExpiry = null,
    ) {}

    /**
     * From the /login response body.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromLogin(array $data): self
    {
        return new self(
            accessToken: Parse::string($data['accessToken'] ?? null) ?? '',
            refreshToken: Parse::string($data['refreshToken'] ?? null) ?? '',
            accessTokenExpiry: Parse::dateTime($data['accessTokenExpiry'] ?? null),
            refreshTokenExpiry: Parse::dateTime($data['refreshTokenExpiry'] ?? null),
        );
    }

    /**
     * A copy of this pair with the access token replaced from the
     * /refresh-access-token response `data`.
     *
     * @param  array<string, mixed>  $data
     */
    public function withRefreshedAccessToken(array $data): self
    {
        return new self(
            accessToken: Parse::string($data['newAccessToken'] ?? null) ?? '',
            refreshToken: $this->refreshToken,
            accessTokenExpiry: Parse::dateTime($data['newAccessTokenExpiry'] ?? null),
            refreshTokenExpiry: $this->refreshTokenExpiry,
        );
    }

    public function isComplete(): bool
    {
        return $this->accessToken !== '' && $this->refreshToken !== '';
    }
}
