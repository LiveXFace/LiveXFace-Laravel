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
];
