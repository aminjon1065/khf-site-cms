<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Source Fixtures
    |--------------------------------------------------------------------------
    |
    | Settings for the development dataset replayed from the Committee's live
    | sites (kchs.tj, khf.tj). Text comes from the committed JSON fixtures in
    | `database/seeders/data/source` and never needs the network; only cover
    | images and leadership photos are fetched, and only when `media` is on.
    |
    */

    'source' => [

        // Where `khf:scrape-source` writes its fixtures and the seeders read
        // them back. Tests point this at their own small fixture set.
        'path' => env('SEED_SOURCE_PATH') ?: database_path('seeders/data/source'),

        // Download images referenced by the fixtures while seeding. Turn off
        // for an offline or CI run: content is then seeded without covers.
        'media' => env('SEED_SOURCE_MEDIA', true),

        // Downloaded files are kept here so re-seeding does not refetch them.
        'cache_path' => storage_path('app/seed-source-media'),

        // Path to a CA bundle, for PHP installs that have none configured
        // (`curl.cainfo` / `openssl.cafile` empty). Empty means "use default".
        'ca_bundle' => env('SEED_SOURCE_CA_BUNDLE', ''),

        'timeout' => (int) env('SEED_SOURCE_TIMEOUT', 20),

    ],

];
