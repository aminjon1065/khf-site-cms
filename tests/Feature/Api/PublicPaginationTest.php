<?php

use Database\Seeders\RegionSeeder;

use function Pest\Laravel\seed;

it('paginates and clamps public catalogue endpoints', function (string $endpoint) {
    if ($endpoint === '/api/v1/regions/directory') {
        seed(RegionSeeder::class);
    }

    $this->getJson("{$endpoint}?per_page=999999")
        ->assertOk()
        ->assertJsonPath('meta.per_page', 50)
        ->assertJsonStructure(['data', 'meta', 'links']);
})->with([
    '/api/v1/documents',
    '/api/v1/instructions',
    '/api/v1/projects',
    '/api/v1/announcements',
    '/api/v1/pages',
    '/api/v1/categories',
    '/api/v1/regions/directory',
]);
