<?php

return [
    /*
    |--------------------------------------------------------------------------
    | LiveXFace API Key
    |--------------------------------------------------------------------------
    | Your API key (lxf_...), created in the console under API Keys.
    */
    'api_key' => env('LIVEXFACE_KEY', ''),

    /*
    |--------------------------------------------------------------------------
    | Base URL
    |--------------------------------------------------------------------------
    | Point this at your LiveXFace instance. For on-prem deployments use your
    | own host, e.g. https://faces.internal.example.com/api/v1
    */
    'base_url' => env('LIVEXFACE_URL', 'https://api.livexface.com/api/v1'),

    /*
    |--------------------------------------------------------------------------
    | Request timeout (seconds)
    |--------------------------------------------------------------------------
    */
    'timeout' => (int) env('LIVEXFACE_TIMEOUT', 30),

    /*
    |--------------------------------------------------------------------------
    | Retries
    |--------------------------------------------------------------------------
    | Off by default. max_retries is the number of attempts after the first.
    | 429 and 503 are retried after their Retry-After (capped at
    | max_retry_delay seconds); network errors and other 5xx only for reads,
    | deletes and calls carrying an idempotency key; other 4xx never.
    */
    'max_retries' => (int) env('LIVEXFACE_MAX_RETRIES', 0),
    'max_retry_delay' => (float) env('LIVEXFACE_MAX_RETRY_DELAY', 60),
];
