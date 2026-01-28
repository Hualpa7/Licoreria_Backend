<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Ajustado para permitir peticiones desde localhost y tu túnel ngrok.
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => [
        'http://localhost',
        'http://localhost:8000',
        'http://localhost:80',
        'http://127.0.0.1',
        'http://127.0.0.1:8000',
        'http://127.0.0.1:80',
        'http://127.0.0.1:5173',
        'https://dusti-unwheedled-daphne.ngrok-free.dev',
    ],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,

];