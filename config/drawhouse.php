<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Base URL
    |--------------------------------------------------------------------------
    |
    | Production is https://api.prod.raffletown.co.uk. Override for a sandbox.
    |
    */
    'base_url' => env('DRAWHOUSE_BASE_URL', 'https://api.prod.raffletown.co.uk'),

    /*
    |--------------------------------------------------------------------------
    | Partner credentials (issued by DrawHouse at onboarding)
    |--------------------------------------------------------------------------
    */
    'email' => env('DRAWHOUSE_EMAIL'),
    'password' => env('DRAWHOUSE_PASSWORD'),

    /*
    |--------------------------------------------------------------------------
    | Affiliate ID
    |--------------------------------------------------------------------------
    |
    | Optional. When set, the SDK never needs to call /v2/partners/me to learn
    | it. When null it is fetched once and cached.
    |
    */
    'affiliate_id' => env('DRAWHOUSE_AFFILIATE_ID'),

    /*
    |--------------------------------------------------------------------------
    | HTTP
    |--------------------------------------------------------------------------
    */
    'timeout' => (int) env('DRAWHOUSE_TIMEOUT', 30),

    /*
    |--------------------------------------------------------------------------
    | Cache
    |--------------------------------------------------------------------------
    |
    | Tokens and the affiliate id are kept in the Laravel cache. `store` null
    | uses the default cache store. TTLs are fallbacks used only when the API
    | response does not tell us when a token expires (seconds).
    |
    */
    'cache' => [
        'store' => env('DRAWHOUSE_CACHE_STORE'),
        'prefix' => 'drawhouse',
        'access_token_ttl' => 55 * 60,
        'refresh_token_ttl' => 30 * 24 * 60 * 60,
        // Seconds knocked off a token's lifetime so we refresh before it lapses.
        'expiry_buffer' => 30,
    ],

];
