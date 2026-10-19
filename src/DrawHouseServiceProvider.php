<?php

declare(strict_types=1);

namespace Digitonic\DrawHouse;

use Digitonic\DrawHouse\Auth\TokenStore;
use Digitonic\DrawHouse\Console\FixturesCommand;
use Digitonic\DrawHouse\Console\SmokeTestCommand;
use Digitonic\DrawHouse\Console\WebhooksCommand;
use Digitonic\DrawHouse\Http\DrawHouseConnector;
use Digitonic\DrawHouse\Webhooks\WebhookVerifier;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;

final class DrawHouseServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/drawhouse.php', 'drawhouse');

        $this->app->singleton(DrawHouseConnector::class, function (Application $app): DrawHouseConnector {
            $config = $app['config']->get('drawhouse');

            return new DrawHouseConnector(
                baseUrl: (string) $config['base_url'],
                timeout: (int) ($config['timeout'] ?? 30),
            );
        });

        $this->app->singleton(TokenStore::class, function (Application $app): TokenStore {
            $config = $app['config']->get('drawhouse.cache', []);

            return new TokenStore(
                cache: $app->make(CacheFactory::class)->store($config['store'] ?? null),
                prefix: (string) ($config['prefix'] ?? 'drawhouse'),
                accessTokenTtl: (int) ($config['access_token_ttl'] ?? 3300),
                refreshTokenTtl: (int) ($config['refresh_token_ttl'] ?? 2592000),
                expiryBuffer: (int) ($config['expiry_buffer'] ?? 30),
            );
        });

        $this->app->singleton(DrawHouse::class, function (Application $app): DrawHouse {
            $config = $app['config']->get('drawhouse');

            return new DrawHouse(
                connector: $app->make(DrawHouseConnector::class),
                tokens: $app->make(TokenStore::class),
                email: (string) ($config['email'] ?? ''),
                password: (string) ($config['password'] ?? ''),
                affiliateId: isset($config['affiliate_id']) && $config['affiliate_id'] !== '' ? (int) $config['affiliate_id'] : null,
            );
        });

        $this->app->alias(DrawHouse::class, 'drawhouse');

        // Built on first resolution, so an app that never receives webhooks
        // needs no secret configured.
        $this->app->singleton(WebhookVerifier::class, function (Application $app): WebhookVerifier {
            $config = $app['config']->get('drawhouse.webhooks', []);

            return new WebhookVerifier(
                secrets: [(string) ($config['secret'] ?? ''), (string) ($config['previous_secret'] ?? '')],
                tolerance: self::webhookTolerance($config),
            );
        });
    }

    /**
     * `drawhouse.webhooks.tolerance`: null (including DRAWHOUSE_WEBHOOK_TOLERANCE=null)
     * disables the timestamp check, a missing or blank value means the default,
     * and anything else must be a whole number of seconds.
     *
     * @param  array<string, mixed>  $config
     */
    private static function webhookTolerance(array $config): ?int
    {
        if (! array_key_exists('tolerance', $config)) {
            return WebhookVerifier::DEFAULT_TOLERANCE;
        }

        $tolerance = $config['tolerance'];

        if ($tolerance === null) {
            return null;
        }

        if (is_string($tolerance)) {
            $tolerance = trim($tolerance);

            if ($tolerance === '') {
                return WebhookVerifier::DEFAULT_TOLERANCE;
            }
        }

        if (is_int($tolerance) || (is_string($tolerance) && ctype_digit($tolerance))) {
            return (int) $tolerance;
        }

        throw new InvalidArgumentException(sprintf(
            'DrawHouse webhook tolerance must be a whole number of seconds or null, %s given',
            is_scalar($tolerance) ? var_export($tolerance, true) : get_debug_type($tolerance),
        ));
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/drawhouse.php' => $this->app->configPath('drawhouse.php'),
            ], 'drawhouse-config');

            // Opt-in only: never scheduled, never run by tests.
            $this->commands([SmokeTestCommand::class, FixturesCommand::class, WebhooksCommand::class]);
        }
    }
}
