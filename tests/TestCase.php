<?php

declare(strict_types=1);

namespace Digitonic\DrawHouse\Tests;

use Digitonic\DrawHouse\DrawHouseServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;
use Saloon\Http\Faking\MockClient;
use Saloon\Laravel\SaloonServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function tearDown(): void
    {
        MockClient::destroyGlobal();

        parent::tearDown();
    }

    /**
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            SaloonServiceProvider::class,
            DrawHouseServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('cache.default', 'array');
        $app['config']->set('drawhouse.base_url', 'https://api.test.raffletown.co.uk');
        $app['config']->set('drawhouse.email', 'partner@example.com');
        $app['config']->set('drawhouse.password', 'super-secret-password');
        $app['config']->set('drawhouse.affiliate_id', null);
    }
}
