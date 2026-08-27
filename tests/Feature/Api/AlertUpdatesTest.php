<?php

use App\Models\Alert;

// Предупреждение живёт часами и меняется: зона расширилась, уровень снижен,
// угроза снята. Без истории читателю неоткуда узнать, свежая ли перед ним
// информация и что именно изменилось.

function alertWithUpdates(array $updates): Alert
{
    return Alert::factory()->published()->create(['updates' => $updates]);
}

it('returns the update history newest first', function () {
    $alert = alertWithUpdates([
        ['at' => '2026-08-22T08:00:00+05:00', 'text' => ['ru' => 'Объявлен жёлтый уровень']],
        ['at' => '2026-08-22T14:30:00+05:00', 'text' => ['ru' => 'Зона расширена на Восе']],
        ['at' => '2026-08-22T11:15:00+05:00', 'text' => ['ru' => 'Уровень повышен до оранжевого']],
    ]);

    $response = $this->getJson("/api/v1/alerts/{$alert->slug}?locale=ru")->assertOk();

    // Свежее состояние читателю важнее начала событий.
    expect($response->json('data.updates.*.text'))->toBe([
        'Зона расширена на Восе',
        'Уровень повышен до оранжевого',
        'Объявлен жёлтый уровень',
    ]);
});

it('skips entries that have no text in the requested language', function () {
    // Показать русскую строку на таджикской версии предупреждения нельзя, а
    // дата без текста не сообщает ничего.
    $alert = alertWithUpdates([
        ['at' => '2026-08-22T08:00:00+05:00', 'text' => ['ru' => 'Только по-русски']],
        ['at' => '2026-08-22T09:00:00+05:00', 'text' => ['ru' => 'Зона расширена', 'tg' => 'Минтақа васеъ шуд']],
    ]);

    $ru = $this->getJson("/api/v1/alerts/{$alert->slug}?locale=ru")->assertOk();
    $tg = $this->getJson("/api/v1/alerts/{$alert->slug}?locale=tg")->assertOk();

    expect($ru->json('data.updates'))->toHaveCount(2)
        ->and($tg->json('data.updates'))->toHaveCount(1)
        ->and($tg->json('data.updates.0.text'))->toBe('Минтақа васеъ шуд');
});

it('ignores malformed entries instead of failing', function () {
    // JSON пишет форма, и старые строки могут не совпадать по форме с текущей.
    // Публичная страница предупреждения не имеет права падать из-за этого.
    $alert = alertWithUpdates([
        ['text' => ['ru' => 'Без времени']],
        ['at' => '2026-08-22T09:00:00+05:00'],
        ['at' => '', 'text' => ['ru' => 'Пустое время']],
        ['at' => '2026-08-22T10:00:00+05:00', 'text' => ['ru' => '   ']],
        ['at' => '2026-08-22T11:00:00+05:00', 'text' => ['ru' => 'Годная запись']],
    ]);

    $response = $this->getJson("/api/v1/alerts/{$alert->slug}?locale=ru")->assertOk();

    expect($response->json('data.updates'))->toHaveCount(1)
        ->and($response->json('data.updates.0.text'))->toBe('Годная запись');
});

it('returns an empty history when the editor added none', function () {
    $alert = alertWithUpdates([]);

    $response = $this->getJson("/api/v1/alerts/{$alert->slug}?locale=ru")->assertOk();

    expect($response->json('data.updates'))->toBe([]);
});

it('omits the history from the alerts list', function () {
    // В списке таймлайн не выводится.
    alertWithUpdates([
        ['at' => '2026-08-22T11:00:00+05:00', 'text' => ['ru' => 'Запись']],
    ]);

    $response = $this->getJson('/api/v1/alerts?locale=ru')->assertOk();

    expect($response->json('data.0'))->not->toHaveKey('updates');
});
