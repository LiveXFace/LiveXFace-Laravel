<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Serupa API Key
    |--------------------------------------------------------------------------
    | Your API key (srp_...), created in the console under API Keys.
    */
    'api_key' => env('SERUPA_KEY', ''),

    /*
    |--------------------------------------------------------------------------
    | Base URL
    |--------------------------------------------------------------------------
    | Point this at your Serupa instance. For on-prem deployments use your
    | own host, e.g. https://faces.internal.example.com/api/v1
    */
    'base_url' => env('SERUPA_URL', 'https://api.serupa.ai/api/v1'),

    /*
    |--------------------------------------------------------------------------
    | Request timeout (seconds)
    |--------------------------------------------------------------------------
    */
    'timeout' => (int) env('SERUPA_TIMEOUT', 30),
];
