<?php

declare(strict_types=1);

namespace Digitonic\DrawHouse\Exceptions;

/**
 * Raised when DrawHouse rejects the partner credentials, rejects a token
 * refresh, or when a request is still unauthorised after the token has been
 * refreshed or re-issued.
 *
 * Login:
 *  - 400 malformed body
 *  - 401 wrong credentials
 *  - 403 the account is blocked; contact the DrawHouse onboarding engineer
 *
 * Refresh:
 *  - 400 a token is missing from the body
 *  - 422 the refresh token has expired or does not match; the client logs in
 *        again automatically (see DrawHouse::reauthenticate())
 */
final class AuthenticationException extends DrawHouseException
{
    public static function fromDrawHouse(DrawHouseException $e, string $context): self
    {
        $reason = match (true) {
            $context === 'login' && $e->status === 400 => 'malformed request body',
            $context === 'login' && $e->status === 401 => 'wrong credentials',
            $context === 'login' && $e->status === 403 => 'the partner account is blocked; contact your DrawHouse onboarding engineer',
            $context === 'token refresh' && $e->status === 400 => 'a token was missing from the refresh body',
            $context === 'token refresh' && $e->status === 422 => 'the refresh token has expired or does not match',
            default => null,
        };

        return new self(
            sprintf('DrawHouse %s failed%s: %s', $context, $reason === null ? '' : ' ('.$reason.')', $e->getMessage()),
            $e->errorCode,
            $e->status,
            $e,
        );
    }

    public static function stillUnauthorised(): self
    {
        return new self('DrawHouse rejected the request as unauthorised after re-authenticating', null, 401);
    }

    public static function missingCredentials(): self
    {
        return new self('DrawHouse credentials are not configured (DRAWHOUSE_EMAIL / DRAWHOUSE_PASSWORD)', null, 0);
    }

    /**
     * Login answered 403: the account is blocked. Retrying will not help.
     */
    public function isAccountBlocked(): bool
    {
        return $this->status === 403;
    }

    /**
     * Refresh answered 422: the refresh token has expired or does not match.
     * The client has already fallen back to a fresh login when you see this.
     */
    public function isRefreshTokenExpired(): bool
    {
        return $this->status === 422;
    }
}
