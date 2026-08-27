<?php

use App\Services\OperationalTelemetry;
use Illuminate\Support\Facades\Cache;

it('reports exact bounded API and queue operational percentiles', function () {
    Cache::forget(OperationalTelemetry::CACHE_KEY);
    $telemetry = app(OperationalTelemetry::class);

    foreach ([10, 20, 30, 40, 500] as $duration) {
        $telemetry->recordApi('api.news.index', $duration === 500 ? 503 : 200, $duration);
    }
    $telemetry->recordApi('api.news.show', 404, 8);
    $telemetry->recordApi('api.health', 200, 5);

    $telemetry->startQueueJob('job-1');
    $telemetry->finishQueueJob('job-1', 'critical');

    $summary = $telemetry->summary();

    expect($summary['api'])
        ->samples->toBe(7)
        // 4xx и 5xx разделены: общее число «ошибок» смешивало бы 404 от
        // краулера с отказом сервера, и по нему нельзя решить, инцидент это
        // или обычный шум.
        ->server_errors->toBe(1)
        ->client_errors->toBe(1)
        ->p95_ms->toBe(500.0)
        ->and($summary['api']['routes'][0])
        ->route->toBe('api.news.index')
        ->samples->toBe(5)
        ->server_errors->toBe(1)
        ->client_errors->toBe(0)
        ->and($summary['queue'])
        ->samples->toBe(1)
        ->failures->toBe(0)
        ->last_processed_at->not->toBeNull();
});

it('says which period the samples cover', function () {
    // Без окна «p95 = 400 мс» нельзя прочитать: двести замеров могли уложиться
    // в минуту пиковой нагрузки или растянуться на трое суток.
    Cache::forget(OperationalTelemetry::CACHE_KEY);
    $telemetry = app(OperationalTelemetry::class);

    expect($telemetry->summary()['api']['window'])
        ->from->toBeNull()
        ->to->toBeNull();

    // Метки времени относительные, а не 2026-08-01: телеметрия кладётся в кэш
    // с TTL семь дней, и TTL считается от «времени внутри путешествия». С
    // фиксированной прошлой датой запись протухала раньше, чем тест успевал
    // её прочитать, — тест начал падать сам собой, когда календарь ушёл
    // дальше этой даты на неделю.
    $start = now()->startOfHour();
    $end = $start->copy()->addHours(2)->addMinutes(30);

    $this->travelTo($start);
    $telemetry->recordApi('api.news.index', 200, 10);
    $this->travelTo($end);
    $telemetry->recordApi('api.news.index', 200, 12);
    $this->travelBack();

    $window = $telemetry->summary()['api']['window'];

    expect($window['from'])->toStartWith($start->toIso8601String())
        ->and($window['to'])->toStartWith($end->toIso8601String());
});
