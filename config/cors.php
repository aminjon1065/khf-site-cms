<?php

/*
 * Cross-Origin Resource Sharing (CORS) configuration for the public API.
 * The Next.js public site (a separate origin) consumes `api/v1/*`; the
 * allowed origins are supplied via the CORS_ALLOWED_ORIGINS env var
 * (comma-separated). Credentials are never shared — the API is stateless.
 */

$origins = array_values(array_filter(array_map(
    'trim',
    explode(',', (string) env('CORS_ALLOWED_ORIGINS', 'http://localhost:3000')),
)));

return [
    'paths' => ['api/*'],

    'allowed_methods' => ['GET', 'POST', 'OPTIONS'],

    'allowed_origins' => $origins,

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['Accept', 'Content-Type', 'X-Requested-With', 'Accept-Language', 'X-Request-ID'],

    'exposed_headers' => ['ETag', 'X-Request-ID'],

    'max_age' => 3600,

    'supports_credentials' => false,
];
