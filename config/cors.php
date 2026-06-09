<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    | NutriMatch frontend (Nuxt 3) runs on a separate origin. Sanctum
    | SPA authentication requires credentials to be included.
    |
    | The allowed_origins value MUST NOT be '*' in production.
    | Set FRONTEND_URL in your .env to the Nuxt frontend URL.
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => [env('FRONTEND_URL', 'http://localhost:3000')],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,
];
