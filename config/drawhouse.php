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
    | Webhooks
    |--------------------------------------------------------------------------
    |
    | Used only when you resolve Digitonic\DrawHouse\Webhooks\WebhookVerifier
    | from the container; construct it yourself instead if you keep secrets
    | elsewhere (e.g. one per endpoint in your database).
    |
    | `secret` is the endpoint's signing secret, shown once by DrawHouse when
    | the endpoint is registered or its secret is rotated. While rotating, put
    | the old one in `previous_secret` so both verify until the switch-over
    | finishes, then clear it.
    |
    | `tolerance` is how far, in seconds, a signature's timestamp may be from
    | now in either direction. Set it (or DRAWHOUSE_WEBHOOK_TOLERANCE) to null
    | to disable the check; left blank it defaults to 300. Not cast here, so
    | that null survives.
    |
    */
    'webhooks' => [
        'secret' => env('DRAWHOUSE_WEBHOOK_SECRET'),
        'previous_secret' => env('DRAWHOUSE_WEBHOOK_PREVIOUS_SECRET'),
        'tolerance' => env('DRAWHOUSE_WEBHOOK_TOLERANCE', 300),
    ],

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
