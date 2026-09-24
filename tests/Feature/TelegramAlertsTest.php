<?php

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

// Audit J-5: critical events of the CMS reach the duty chat in Telegram —
// once per kind in ten minutes, never at the cost of the request that logged
// them.

beforeEach(function () {
    Cache::flush();
    config([
        'services.telegram_alerts.bot_token' => 'test-token',
        'services.telegram_alerts.chat_id' => '-100123',
    ]);
});

function telegramAccepts(): void
{
    Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);
}

it('sends a critical log entry to the duty chat', function () {
    telegramAccepts();

    Log::critical('scheduled_task_failed', [
        'event' => 'scheduled_task_failed',
        'task' => 'backup:create',
        'error' => 'Диск заполнен',
    ]);

    Http::assertSentCount(1);
    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.telegram.org/bottest-token/sendMessage'
        && $request['chat_id'] === '-100123'
        && str_contains($request['text'], 'scheduled_task_failed')
        && str_contains($request['text'], 'task: backup:create')
        && str_contains($request['text'], 'error: Диск заполнен'));
});

it('leaves lesser entries in the log', function () {
    telegramAccepts();

    Log::error('revalidation_slow');
    Log::warning('scheduled_task_skipped');
    Log::info('queue_heartbeat');

    Http::assertNothingSent();
});

it('sends nothing until the bot is set up', function () {
    telegramAccepts();
    config(['services.telegram_alerts.chat_id' => null]);

    Log::critical('queue_job_failed');

    Http::assertNothingSent();
});

it('sends a burst of the same failure once in ten minutes', function () {
    telegramAccepts();

    foreach (range(1, 5) as $attempt) {
        Log::critical('queue_job_failed', ['job' => 'App\\Jobs\\RevalidateFrontend']);
    }
    Log::critical('queue_job_failed', ['job' => 'App\\Jobs\\PerformMediaConversions']);

    Http::assertSentCount(2);

    $this->travel(11)->minutes();
    Log::critical('queue_job_failed', ['job' => 'App\\Jobs\\RevalidateFrontend']);

    Http::assertSentCount(3);
});

it('carries on when Telegram refuses the message', function () {
    Http::fake(['api.telegram.org/*' => Http::response(['ok' => false], 401)]);

    Log::critical('queue_busy');

    Http::assertSentCount(1);
});

it('sends a test alert on request', function () {
    telegramAccepts();

    $this->artisan('ops:alert-test')->assertSuccessful();

    Http::assertSent(fn (Request $request): bool => str_contains($request['text'], 'Пробное оповещение'));
});

it('says what is missing when alerts are not set up', function () {
    config(['services.telegram_alerts.bot_token' => null]);

    $this->artisan('ops:alert-test')
        ->expectsOutputToContain('TELEGRAM_ALERTS_BOT_TOKEN')
        ->assertFailed();
});

it('reports a test alert Telegram refused', function () {
    Http::fake(['api.telegram.org/*' => Http::response(['ok' => false], 401)]);

    $this->artisan('ops:alert-test')->assertFailed();
});
