<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Idemity API Key
    |--------------------------------------------------------------------------
    | Your API key (idm_...), created in the console under API Keys.
    */
    'api_key' => env('IDEMITY_KEY', ''),

    /*
    |--------------------------------------------------------------------------
    | Base URL
    |--------------------------------------------------------------------------
    | Point this at your Idemity instance. For on-prem deployments use your
    | own host, e.g. https://faces.internal.example.com/api/v1
    */
    'base_url' => env('IDEMITY_URL', 'https://api.idemity.com/api/v1'),

    /*
    |--------------------------------------------------------------------------
    | Request timeout (seconds)
    |--------------------------------------------------------------------------
    */
    'timeout' => (int) env('IDEMITY_TIMEOUT', 30),
];
