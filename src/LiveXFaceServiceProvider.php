<?php

namespace LiveXFace;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\ServiceProvider;

class LiveXFaceServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/livexface.php', 'livexface');

        $this->app->singleton(LiveXFaceClient::class, function (Application $app) {
            $config = $app['config']['livexface'];

            return new LiveXFaceClient(
                apiKey: $config['api_key'] ?? '',
                baseUrl: $config['base_url'] ?? 'https://api.livexface.com/api/v1',
                timeout: (int) ($config['timeout'] ?? 30),
                http: $app->make(HttpFactory::class),
                maxRetries: (int) ($config['max_retries'] ?? 0),
                maxRetryDelay: (float) ($config['max_retry_delay'] ?? 60),
            );
        });

        $this->app->alias(LiveXFaceClient::class, 'livexface');
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/livexface.php' => config_path('livexface.php'),
            ], 'livexface-config');
        }
    }
}
