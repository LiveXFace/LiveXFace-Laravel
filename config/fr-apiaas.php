<?php

return [
    /*
    |--------------------------------------------------------------------------
    | FR-APIaaS API Key
    |--------------------------------------------------------------------------
    | Your API key (fras_...), created in the console under API Keys.
    */
    'api_key' => env('FR_APIAAS_KEY', ''),

    /*
    |--------------------------------------------------------------------------
    | Base URL
    |--------------------------------------------------------------------------
    | Point this at your FR-APIaaS instance. For on-prem deployments use your
    | own host, e.g. https://faces.internal.example.com/api/v1
    */
    'base_url' => env('FR_APIAAS_URL', 'https://api.fr-apiaas.io/api/v1'),

    /*
    |--------------------------------------------------------------------------
    | Request timeout (seconds)
    |--------------------------------------------------------------------------
    */
    'timeout' => (int) env('FR_APIAAS_TIMEOUT', 30),
];
