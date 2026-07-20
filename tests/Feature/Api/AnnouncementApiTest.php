<?php

use App\Enums\AnnouncementKind;
use App\Enums\ContentStatus;
use App\Models\Announcement;

it('returns only publicly visible announcements, open ones first', function () {
    Announcement::factory()->published()->create([
        'deadline' => now()->subDay(), 'title' => ['ru' => 'Закрытая', 'tg' => '', 'en' => ''],
    ]);
    Announcement::factory()->published()->create([
        'deadline' => now()->addWeek(), 'title' => ['ru' => 'Открытая', 'tg' => '', 'en' => ''],
    ]);
    Announcement::factory()->create(['status' => ContentStatus::Draft]);

    $response = $this->getJson('/api/v1/announcements');

    $response->assertOk()->assertJsonCount(2, 'data');
    expect($response->json('data.0.title'))->toBe('Открытая')
        ->and($response->json('data.0.open'))->toBeTrue()
        ->and($response->json('data.1.open'))->toBeFalse();
});

it('derives open from the deadline and formats the label', function () {
    $deadline = now()->addMonths(3);
    Announcement::factory()->published()->create(['deadline' => $deadline]);

    $item = $this->getJson('/api/v1/announcements')->json('data.0');

    expect($item['open'])->toBeTrue()
        ->and($item['deadline'])->toBe('до '.$deadline->format('d.m.Y'));
});

it('treats a null deadline as open and shows бессрочно', function () {
    Announcement::factory()->published()->create(['deadline' => null]);

    $item = $this->getJson('/api/v1/announcements')->json('data.0');

    expect($item['open'])->toBeTrue()
        ->and($item['deadline'])->toBe('бессрочно');
});

it('filters by kind', function () {
    Announcement::factory()->published()->create(['kind' => AnnouncementKind::Vacancy]);
    Announcement::factory()->published()->create(['kind' => AnnouncementKind::Tender]);

    $this->getJson('/api/v1/announcements?kind=tender')->assertOk()->assertJsonCount(1, 'data');
});

it('serves the requested locale with a ru fallback', function () {
    Announcement::factory()->published()->create([
        'title' => ['ru' => 'Русский', 'tg' => 'Тоҷикӣ', 'en' => ''],
    ]);

    expect($this->getJson('/api/v1/announcements?locale=tg')->json('data.0.title'))->toBe('Тоҷикӣ')
        ->and($this->getJson('/api/v1/announcements?locale=en')->json('data.0.title'))->toBe('Русский');
});

it('exposes only public fields', function () {
    Announcement::factory()->published()->create();

    $item = $this->getJson('/api/v1/announcements')->json('data.0');

    expect(array_keys($item))->toEqualCanonicalizing([
        'kind', 'kind_label', 'title', 'org', 'desc', 'deadline', 'open',
    ]);
});
