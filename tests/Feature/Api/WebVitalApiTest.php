<?php

use App\Models\WebVitalSample;
use App\Services\WebVitalsReportService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;

use function Pest\Laravel\artisan;

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function validWebVitalPayload(array $overrides = []): array
{
    return [
        'name' => 'LCP',
        'value' => 2140.25,
        'id' => 'v4-1722170000000-123456789',
        'path' => '/ru/news/public-article?utm_source=test',
        'locale' => 'ru',
        'device' => 'mobile',
        'navigation_type' => 'navigate',
        ...$overrides,
    ];
}

beforeEach(function () {
    config(['services.frontend.rum_secret' => 'test-rum-secret']);
    Cache::forget(WebVitalsReportService::CACHE_KEY);
});

it('stores an anonymous normalized sample and calculates its rating on the server', function () {
    $response = $this
        ->withHeader('X-RUM-Key', 'test-rum-secret')
        ->postJson('/api/v1/vitals', validWebVitalPayload());

    $response->assertAccepted()->assertJson(['accepted' => true]);

    $sample = WebVitalSample::query()->sole();
    expect($sample->metric)->toBe('LCP')
        ->and($sample->value)->toBe(2140.25)
        ->and($sample->rating)->toBe('good')
        ->and($sample->route)->toBe('/ru/news/[slug]')
        ->and($sample->locale)->toBe('ru')
        ->and($sample->device)->toBe('mobile')
        ->and($sample->getAttributes())->not->toHaveKeys(['ip_address', 'user_agent', 'session_id']);
});

it('deduplicates retries by metric id and invalidates the cached report', function () {
    $service = app(WebVitalsReportService::class);
    expect($service->summary()['total_samples'])->toBe(0);

    $request = $this->withHeader('X-RUM-Key', 'test-rum-secret');
    $request->postJson('/api/v1/vitals', validWebVitalPayload())->assertAccepted();
    $request->postJson('/api/v1/vitals', validWebVitalPayload(['value' => 9999]))->assertAccepted();

    expect(WebVitalSample::query()->count())->toBe(1)
        ->and($service->summary()['total_samples'])->toBe(1);
});

it('rejects unauthenticated and malformed telemetry', function () {
    $this->postJson('/api/v1/vitals', validWebVitalPayload())->assertForbidden();

    $this->withHeader('X-RUM-Key', 'test-rum-secret')
        ->postJson('/api/v1/vitals', validWebVitalPayload([
            'name' => 'FCP',
            'path' => 'https://example.test/private?email=user@example.test',
        ]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name', 'path']);

    $this->withHeader('X-RUM-Key', 'test-rum-secret')
        ->postJson('/api/v1/vitals', validWebVitalPayload([
            'name' => 'CLS',
            'value' => 11,
        ]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('value');
});

it('rate limits the public ingestion endpoint', function () {
    RateLimiter::for(
        'rum',
        fn (Request $request): Limit => Limit::perMinute(2)->by($request->ip()),
    );

    $request = $this->withHeader('X-RUM-Key', 'test-rum-secret');
    $request->postJson('/api/v1/vitals', validWebVitalPayload(['id' => 'sample-1']))->assertAccepted();
    $request->postJson('/api/v1/vitals', validWebVitalPayload(['id' => 'sample-2']))->assertAccepted();
    $request->postJson('/api/v1/vitals', validWebVitalPayload(['id' => 'sample-3']))
        ->assertTooManyRequests()
        ->assertHeader('Retry-After');
});

it('calculates exact p75 by metric route and device', function () {
    foreach ([
        ['LCP', [1000, 2000, 3000, 5000]],
        ['INP', [50, 100, 250, 600]],
        ['CLS', [0.01, 0.05, 0.15, 0.4]],
    ] as [$metric, $values]) {
        foreach ($values as $value) {
            WebVitalSample::factory()->metric($metric, $value)->create([
                'route' => '/ru/news',
                'device' => 'mobile',
            ]);
        }
    }

    $report = app(WebVitalsReportService::class)->summary();

    expect($report['total_samples'])->toBe(12)
        ->and($report['metrics'][0])->toMatchArray([
            'metric' => 'LCP',
            'p75' => 3000.0,
            'samples' => 4,
            'rating' => 'needs-improvement',
            'provisional' => true,
        ])
        ->and($report['metrics'][1]['p75'])->toBe(250.0)
        ->and($report['metrics'][2]['p75'])->toBe(0.15)
        ->and($report['routes'][0]['route'])->toBe('/ru/news')
        ->and($report['devices'][0]['device'])->toBe('mobile');
});

it('prunes only samples outside the retention window', function () {
    WebVitalSample::factory()->create(['created_at' => now()->subDays(36)]);
    $recent = WebVitalSample::factory()->create(['created_at' => now()->subDays(34)]);

    artisan('rum:prune --days=35')
        ->expectsOutput('Deleted 1 Web Vitals sample(s) older than 35 days.')
        ->assertSuccessful();

    $this->assertModelExists($recent);
    expect(WebVitalSample::query()->count())->toBe(1);
});
