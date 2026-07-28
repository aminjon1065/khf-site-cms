<?php

use App\Enums\ContentStatus;
use App\Models\Alert;
use App\Models\Announcement;
use App\Models\Instruction;
use App\Models\News;
use App\Models\Page;
use App\Models\Project;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;

/**
 * @param  array<string, mixed>  $document
 * @return array<string, mixed>
 */
function khfResolveOpenApiReference(array $document, string $reference): array
{
    $value = $document;

    foreach (explode('/', ltrim($reference, '#/')) as $segment) {
        $segment = str_replace(['~1', '~0'], ['/', '~'], $segment);
        $value = $value[$segment] ?? null;
    }

    expect($value)->toBeArray("OpenAPI reference [{$reference}] cannot be resolved.");

    return $value;
}

/**
 * @param  array<string, mixed>  $schema
 * @param  array<string, mixed>  $document
 * @return list<string>
 */
function khfOpenApiValidationErrors(
    mixed $value,
    array $schema,
    array $document,
    string $path = '$',
): array {
    if (isset($schema['$ref'])) {
        return khfOpenApiValidationErrors(
            $value,
            khfResolveOpenApiReference($document, $schema['$ref']),
            $document,
            $path,
        );
    }

    if (isset($schema['allOf'])) {
        return array_values(array_merge(...array_map(
            fn (array $candidate): array => khfOpenApiValidationErrors($value, $candidate, $document, $path),
            $schema['allOf'],
        )));
    }

    $alternatives = $schema['oneOf'] ?? $schema['anyOf'] ?? null;

    if (is_array($alternatives)) {
        foreach ($alternatives as $candidate) {
            if (khfOpenApiValidationErrors($value, $candidate, $document, $path) === []) {
                return [];
            }
        }

        return ["{$path} does not match any allowed OpenAPI schema."];
    }

    if (isset($schema['enum']) && ! in_array($value, $schema['enum'], true)) {
        return ["{$path} contains a value outside the documented enum."];
    }

    if (array_key_exists('const', $schema) && $value !== $schema['const']) {
        return ["{$path} does not match the documented constant."];
    }

    $types = is_array($schema['type'] ?? null)
        ? $schema['type']
        : [$schema['type'] ?? null];

    if (count($types) > 1) {
        foreach ($types as $type) {
            if (khfOpenApiValidationErrors($value, [...$schema, 'type' => $type], $document, $path) === []) {
                return [];
            }
        }

        return ["{$path} does not match any documented type."];
    }

    $type = $types[0];
    $validType = match ($type) {
        null => true,
        'null' => $value === null,
        'string' => is_string($value),
        'integer' => is_int($value),
        'number' => is_int($value) || is_float($value),
        'boolean' => is_bool($value),
        'array' => is_array($value) && array_is_list($value),
        'object' => is_array($value) && ($value === [] || ! array_is_list($value)),
        default => false,
    };

    if (! $validType) {
        return ["{$path} must be of OpenAPI type [{$type}]."];
    }

    if ($type === 'array') {
        $errors = [];

        foreach ($value as $index => $item) {
            $errors = [
                ...$errors,
                ...khfOpenApiValidationErrors($item, $schema['items'] ?? [], $document, "{$path}[{$index}]"),
            ];
        }

        return $errors;
    }

    if ($type !== 'object') {
        return [];
    }

    $errors = [];

    foreach ($schema['required'] ?? [] as $required) {
        if (! array_key_exists($required, $value)) {
            $errors[] = "{$path}.{$required} is required by OpenAPI.";
        }
    }

    foreach ($schema['properties'] ?? [] as $name => $propertySchema) {
        if (array_key_exists($name, $value)) {
            $errors = [
                ...$errors,
                ...khfOpenApiValidationErrors($value[$name], $propertySchema, $document, "{$path}.{$name}"),
            ];
        }
    }

    return $errors;
}

/**
 * @return array<string, mixed>
 */
function khfOpenApiDocument(): array
{
    $contents = file_get_contents(base_path('openapi/openapi.json'));

    expect($contents)->not->toBeFalse();

    return json_decode($contents, true, flags: JSON_THROW_ON_ERROR);
}

it('documents every public API operation exactly once', function () {
    $document = khfOpenApiDocument();
    $documented = collect($document['paths'])
        ->flatMap(fn (array $operations, string $path) => collect($operations)
            ->keys()
            ->filter(fn (string $method): bool => in_array($method, ['get', 'post', 'put', 'patch', 'delete'], true))
            ->map(fn (string $method): string => strtoupper($method).' '.$path))
        ->sort()
        ->values()
        ->all();

    $registered = collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($route): bool => str_starts_with($route->uri(), 'api/v1/'))
        ->flatMap(function ($route): array {
            $path = '/'.substr($route->uri(), strlen('api/v1/'));

            return collect($route->methods())
                ->reject(fn (string $method): bool => $method === 'HEAD')
                ->map(fn (string $method): string => "{$method} {$path}")
                ->all();
        })
        ->sort()
        ->values()
        ->all();

    expect($document['openapi'])->toBe('3.1.0')
        ->and($documented)->toBe($registered)
        // 25 исходных операций + `/leadership` и `/structure`, появившиеся
        // вместе с переносом руководства и структуры Комитета в CMS (C-1).
        ->and($documented)->toHaveCount(27);
});

it('keeps successful responses for every operation inside the OpenAPI contract', function () {
    $this->seed(DatabaseSeeder::class);
    Cache::put('health.scheduler.last_run', now()->toIso8601String(), 60);
    config(['services.frontend.rum_secret' => 'openapi-rum-secret']);

    $alert = Alert::query()
        ->where('status', ContentStatus::Published->value)
        ->firstOrFail();
    $alert->update(['slug' => $alert->slug ?? 'openapi-contract-alert']);

    $slugs = [
        'alert' => $alert->slug,
        'announcement' => Announcement::query()->where('status', ContentStatus::Published->value)->value('slug'),
        'instruction' => Instruction::query()->where('status', ContentStatus::Published->value)->value('slug'),
        'news' => News::query()->where('status', ContentStatus::Published->value)->value('slug'),
        'page' => Page::query()->where('status', ContentStatus::Published->value)->value('slug'),
        'project' => Project::query()->where('status', ContentStatus::Published->value)->value('slug'),
    ];

    foreach ($slugs as $type => $slug) {
        expect($slug)->not->toBeNull("The seeded {$type} fixture must expose a published slug.");
    }

    $requests = [
        ['get', '/health', '/health'],
        ['get', '/ready', '/ready'],
        ['get', '/home', '/home?locale=ru'],
        ['get', '/news', '/news?locale=ru'],
        ['get', '/news/{slug}', "/news/{$slugs['news']}?locale=ru"],
        ['get', '/alerts', '/alerts?locale=ru'],
        ['get', '/alerts/active', '/alerts/active?locale=ru'],
        ['get', '/alerts/{slug}', "/alerts/{$slugs['alert']}?locale=ru"],
        ['get', '/instructions', '/instructions?locale=ru'],
        ['get', '/instructions/{slug}', "/instructions/{$slugs['instruction']}?locale=ru"],
        ['get', '/documents', '/documents?locale=ru'],
        ['get', '/projects', '/projects?locale=ru'],
        ['get', '/projects/{slug}', "/projects/{$slugs['project']}?locale=ru"],
        ['get', '/announcements', '/announcements?locale=ru'],
        ['get', '/announcements/{slug}', "/announcements/{$slugs['announcement']}?locale=ru"],
        ['get', '/pages', '/pages?locale=ru'],
        ['get', '/pages/{slug}', "/pages/{$slugs['page']}?locale=ru"],
        ['get', '/categories', '/categories?locale=ru'],
        ['get', '/regions', '/regions?locale=ru'],
        ['get', '/regions/directory', '/regions/directory?locale=ru'],
        ['get', '/menu', '/menu?locale=ru'],
        ['get', '/settings', '/settings?locale=ru'],
        ['get', '/search', '/search?q=пожар&locale=ru'],
        ['post', '/submissions', '/submissions', [
            'name' => 'Контрактный тест',
            'email' => 'contract@example.com',
            'topic' => 'Проверка публичного API',
            'message' => 'Проверяем соответствие успешного ответа опубликованной схеме.',
            'consent' => true,
        ]],
        ['post', '/vitals', '/vitals', [
            'name' => 'LCP',
            'value' => 1800,
            'id' => 'openapi-contract-vital',
            'path' => '/ru/news/openapi-contract',
            'locale' => 'ru',
            'device' => 'desktop',
            'navigation_type' => 'navigate',
        ], ['X-RUM-Key' => 'openapi-rum-secret']],
    ];
    $document = khfOpenApiDocument();

    foreach ($requests as $request) {
        [$method, $template, $uri] = $request;
        $payload = $request[3] ?? [];
        $headers = $request[4] ?? [];
        $response = $method === 'post'
            ? $this->withHeaders($headers)->postJson("/api/v1{$uri}", $payload)
            : $this->getJson("/api/v1{$uri}");
        $status = (string) $response->getStatusCode();
        $response->assertSuccessful();

        $responseContract = $document['paths'][$template][$method]['responses'][$status] ?? null;
        expect($responseContract)->toBeArray("Missing {$status} response for ".strtoupper($method)." {$template}.");

        if (isset($responseContract['$ref'])) {
            $responseContract = khfResolveOpenApiReference($document, $responseContract['$ref']);
        }

        $schema = $responseContract['content']['application/json']['schema'] ?? null;
        expect($schema)->toBeArray('Missing JSON schema for '.strtoupper($method)." {$template}.");

        $errors = khfOpenApiValidationErrors($response->json(), $schema, $document);
        expect($errors)->toBeEmpty(
            strtoupper($method)." {$template} violates OpenAPI:\n".implode("\n", $errors),
        );
    }
});
