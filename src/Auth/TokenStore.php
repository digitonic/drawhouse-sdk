<?php

declare(strict_types=1);

namespace Digitonic\DrawHouse\Auth;

use Carbon\CarbonImmutable;
use Digitonic\DrawHouse\DTO\TokenPair;
use Illuminate\Contracts\Cache\Repository;

/**
 * Keeps the token pair (and the affiliate id) in the Laravel cache.
 *
 * Keys:
 *  - access_token          the token used on the `access-token` header; expires early (buffer)
 *  - refresh_token         lives for the refresh token's full lifetime
 *  - refresh_access_token  the access token issued with the refresh token, kept for the
 *                          refresh request body only (it must be sent even once expired)
 *  - refresh_token_expiry  RFC 3339 expiry of the refresh token, so a refreshed access
 *                          token can be stored under the same lifetime
 *  - affiliate_id          forever
 *
 * Nothing here is ever logged.
 */
final class TokenStore
{
    public function __construct(
        private readonly Repository $cache,
        private readonly string $prefix = 'drawhouse',
        private readonly int $accessTokenTtl = 3300,
        private readonly int $refreshTokenTtl = 2592000,
        private readonly int $expiryBuffer = 30,
    ) {}

    /**
     * Store a full pair from /login.
     */
    public function put(TokenPair $pair): void
    {
        $refreshTtl = $this->ttlFor($pair->refreshTokenExpiry, $this->refreshTokenTtl);

        if ($refreshTtl > 0) {
            $this->cache->put($this->key('refresh_token'), $pair->refreshToken, $refreshTtl);
            $this->cache->put(
                $this->key('refresh_token_expiry'),
                CarbonImmutable::now('UTC')->addSeconds($refreshTtl)->toRfc3339String(),
                $refreshTtl,
            );
        } else {
            $this->cache->forget($this->key('refresh_token'));
            $this->cache->forget($this->key('refresh_token_expiry'));
        }

        $this->putAccessToken($pair->accessToken, $pair->accessTokenExpiry);
    }

    /**
     * Store a new access token from /refresh-access-token. The refresh token
     * is unchanged, so only the access-side keys move.
     */
    public function putAccessToken(string $accessToken, ?CarbonImmutable $expiresAt): void
    {
        $accessTtl = $this->ttlFor($expiresAt, $this->accessTokenTtl);

        if ($accessTtl > 0) {
            $this->cache->put($this->key('access_token'), $accessToken, $accessTtl);
        } else {
            $this->cache->forget($this->key('access_token'));
        }

        $refreshTtl = $this->ttlFor($this->refreshTokenExpiry(), $this->refreshTokenTtl);

        if ($refreshTtl > 0) {
            $this->cache->put($this->key('refresh_access_token'), $accessToken, $refreshTtl);
        }
    }

    public function accessToken(): ?string
    {
        return $this->string('access_token');
    }

    public function refreshToken(): ?string
    {
        return $this->string('refresh_token');
    }

    /**
     * The access token paired with the current refresh token, for the refresh
     * request body only. Unlike accessToken() it survives access-token expiry.
     */
    public function accessTokenForRefresh(): ?string
    {
        return $this->string('refresh_access_token') ?? $this->accessToken();
    }

    public function refreshTokenExpiry(): ?CarbonImmutable
    {
        $value = $this->string('refresh_token_expiry');

        return $value === null ? null : CarbonImmutable::parse($value)->utc();
    }

    public function forgetTokens(): void
    {
        foreach (['access_token', 'refresh_token', 'refresh_access_token', 'refresh_token_expiry'] as $key) {
            $this->cache->forget($this->key($key));
        }
    }

    public function affiliateId(): ?int
    {
        $id = $this->cache->get($this->key('affiliate_id'));

        return is_int($id) ? $id : (is_numeric($id) ? (int) $id : null);
    }

    public function putAffiliateId(int $affiliateId): void
    {
        $this->cache->forever($this->key('affiliate_id'), $affiliateId);
    }

    public function forgetAffiliateId(): void
    {
        $this->cache->forget($this->key('affiliate_id'));
    }

    private function string(string $name): ?string
    {
        $value = $this->cache->get($this->key($name));

        return is_string($value) && $value !== '' ? $value : null;
    }

    private function ttlFor(?CarbonImmutable $expiresAt, int $fallback): int
    {
        if ($expiresAt === null) {
            return max(0, $fallback - $this->expiryBuffer);
        }

        $seconds = (int) ceil(CarbonImmutable::now('UTC')->diffInSeconds($expiresAt, false));

        return max(0, $seconds - $this->expiryBuffer);
    }

    private function key(string $name): string
    {
        return $this->prefix.':'.$name;
    }
}
