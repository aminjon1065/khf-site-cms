<?php

use App\Jobs\RevalidateFrontend;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\StrayRequestException;
use Illuminate\Queue\Middleware\ThrottlesExceptions;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

beforeEach(function () {
    config([
        'services.frontend.revalidation_url' => 'https://frontend.example.test/api/revalidate',
        'services.frontend.revalidation_secret' => 'test-secret',
    ]);
});

function revalidationJob(): RevalidateFrontend
{
    return new RevalidateFrontend(
        type: 'news',
        id: 42,
        slug: 'storm-update',
        locales: ['ru', 'tj'],
        event: 'published',
    );
}

it('sends the frontend revalidation request', function () {
    Http::fake([
        'frontend.example.test/*' => Http::response(['revalidated' => true]),
    ]);

    revalidationJob()->handle();

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://frontend.example.test/api/revalidate'
        && $request->hasHeader('Authorization', 'Bearer test-secret')
        && $request->data() === [
            'type' => 'news',
            'id' => 42,
            'slug' => 'storm-update',
            'locales' => ['ru', 'tj'],
            'event' => 'published',
            'tags' => [
                'cms:news:ru',
                'cms:news:storm-update:ru',
                'cms:home:ru',
                'cms:news:tj',
                'cms:news:storm-update:tj',
                'cms:home:tj',
                'cms:sitemap',
            ],
        ]);
});

it('does not send a request when revalidation is disabled', function () {
    config(['services.frontend.revalidation_url' => '']);
    Http::fake();

    revalidationJob()->handle();

    Http::assertNothingSent();
});

it('blocks HTTP requests that have not been explicitly faked', function () {
    expect(fn () => Http::get('https://stray-request.example.test'))
        ->toThrow(StrayRequestException::class);
});

it('does not break synchronous publication when the frontend is unavailable', function () {
    config(['queue.default' => 'sync']);
    Http::fake([
        'frontend.example.test/*' => Http::failedConnection(),
    ]);

    revalidationJob()->handle();

    Http::assertSentCount(1);
});

it('rethrows connection failures for asynchronous queue retries', function () {
    config(['queue.default' => 'database']);
    Http::fake([
        'frontend.example.test/*' => Http::failedConnection(),
    ]);

    expect(fn () => revalidationJob()->handle())
        ->toThrow(ConnectionException::class);
});

it('rethrows unauthorized and server errors for queue retries', function (int $status) {
    config(['queue.default' => 'database']);
    Http::fake([
        'frontend.example.test/*' => Http::response(['error' => 'failed'], $status),
    ]);

    expect(fn () => revalidationJob()->handle())
        ->toThrow(RequestException::class);
})->with([401, 500]);

it('succeeds when the queue retries after a transient server error', function () {
    config(['queue.default' => 'database']);
    Http::fakeSequence()
        ->push(['error' => 'unavailable'], 503)
        ->push(['revalidated' => true], 200);

    $job = revalidationJob();

    expect(fn () => $job->handle())->toThrow(RequestException::class);
    $job->handle();

    Http::assertSentCount(2);
});

it('is unique by its granular tags and throttles repeated exceptions', function () {
    $job = revalidationJob();
    $sameTags = new RevalidateFrontend(
        type: 'news',
        id: 42,
        slug: 'storm-update',
        locales: ['ru', 'tj'],
        event: 'updated',
    );

    expect($job)->toBeInstanceOf(ShouldBeUnique::class)
        ->and($job->timeout)->toBe(10)
        ->and($job->uniqueFor)->toBe(30)
        ->and($job->uniqueId())->toBe($sameTags->uniqueId())
        ->and($job->middleware())->toHaveCount(1)
        ->and($job->middleware()[0])->toBeInstanceOf(ThrottlesExceptions::class);
});

it('raises a monitoring alert after permanent failure', function () {
    Log::spy();
    $exception = new RuntimeException('frontend unavailable');

    revalidationJob()->failed($exception);

    Log::shouldHaveReceived('critical')
        ->once()
        ->withArgs(fn (string $message, array $context): bool => $message === 'Frontend cache revalidation failed permanently.'
            && $context['content_type'] === 'news'
            && $context['content_id'] === 42
            && $context['error'] === 'frontend unavailable');
});
