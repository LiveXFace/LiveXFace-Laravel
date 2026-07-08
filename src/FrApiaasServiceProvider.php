<?php

namespace FrApiaas;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\ServiceProvider;

class FrApiaasServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/fr-apiaas.php', 'fr-apiaas');

        $this->app->singleton(FrClient::class, function (Application $app) {
            $config = $app['config']['fr-apiaas'];

            return new FrClient(
                apiKey: $config['api_key'] ?? '',
                baseUrl: $config['base_url'] ?? 'https://api.fr-apiaas.io/api/v1',
                timeout: (int) ($config['timeout'] ?? 30),
                http: $app->make(HttpFactory::class),
            );
        });

        $this->app->alias(FrClient::class, 'fr-apiaas');
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/fr-apiaas.php' => config_path('fr-apiaas.php'),
            ], 'fr-apiaas-config');
        }
    }
}
