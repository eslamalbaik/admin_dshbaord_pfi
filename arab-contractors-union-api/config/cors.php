<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie', 'login', 'register', 'logout'],

    'allowed_methods' => ['*'],

    'allowed_origins' => array_values(array_unique(array_filter([
        // Production front-ends (from .env)
        env('FRONTEND_URL'),
        env('LANDING_URL'),
        // Same origins with/without the "www." prefix — browsers treat
        // www.example.com and example.com as distinct origins for CORS.
        preg_replace('#^(https?://)www\.#', '$1', (string) env('FRONTEND_URL')) ?: null,
        preg_replace('#^(https?://)(?!www\.)#', '$1www.', (string) env('FRONTEND_URL')) ?: null,
        preg_replace('#^(https?://)www\.#', '$1', (string) env('LANDING_URL')) ?: null,
        preg_replace('#^(https?://)(?!www\.)#', '$1www.', (string) env('LANDING_URL')) ?: null,
        // Local development
        'http://localhost:3000', 'http://localhost:5173', 'http://localhost:5174', 'http://localhost:8080', 'http://localhost:8081', 'http://localhost:4173',
        'http://127.0.0.1:3000', 'http://127.0.0.1:5173', 'http://127.0.0.1:5174', 'http://127.0.0.1:8080', 'http://127.0.0.1:8081', 'http://127.0.0.1:4173',
    ]))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 86400,

    'supports_credentials' => true,

];
