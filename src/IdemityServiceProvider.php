<?php

namespace Idemity;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\ServiceProvider;

class IdemityServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/idemity.php', 'idemity');

        $this->app->singleton(IdemityClient::class, function (Application $app) {
            $config = $app['config']['idemity'];

            return new IdemityClient(
                apiKey: $config['api_key'] ?? '',
                baseUrl: $config['base_url'] ?? 'https://api.idemity.com/api/v1',
                timeout: (int) ($config['timeout'] ?? 30),
                http: $app->make(HttpFactory::class),
            );
        });

        $this->app->alias(IdemityClient::class, 'idemity');
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/idemity.php' => config_path('idemity.php'),
            ], 'idemity-config');
        }
    }
}
