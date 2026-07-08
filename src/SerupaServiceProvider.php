<?php

namespace Serupa;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\ServiceProvider;

class SerupaServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/serupa.php', 'serupa');

        $this->app->singleton(SerupaClient::class, function (Application $app) {
            $config = $app['config']['serupa'];

            return new SerupaClient(
                apiKey: $config['api_key'] ?? '',
                baseUrl: $config['base_url'] ?? 'https://api.serupa.ai/api/v1',
                timeout: (int) ($config['timeout'] ?? 30),
                http: $app->make(HttpFactory::class),
            );
        });

        $this->app->alias(SerupaClient::class, 'serupa');
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/serupa.php' => config_path('serupa.php'),
            ], 'serupa-config');
        }
    }
}
