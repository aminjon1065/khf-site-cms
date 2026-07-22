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

    $response = $this->getJson('/api/v1/announcements?locale=ru');

    $response->assertOk()->assertJsonCount(2, 'data');
    expect($response->json('data.0.title'))->toBe('Открытая')
        ->and($response->json('data.0.open'))->toBeTrue()
        ->and($response->json('data.1.open'))->toBeFalse();
});

it('derives open from the deadline and formats the label', function () {
    $deadline = now()->addMonths(3);
    Announcement::factory()->published()->create(['deadline' => $deadline]);

    $item = $this->getJson('/api/v1/announcements?locale=ru')->json('data.0');

    expect($item['open'])->toBeTrue()
        ->and($item['deadline'])->toBe('до '.$deadline->format('d.m.Y'))
        ->and($item['deadline_at'])->toBe($deadline->toDateString())
        ->and($item['deadline_state'])->toBe('open');
});

it('treats a null deadline as open and shows бессрочно', function () {
    Announcement::factory()->published()->create(['deadline' => null]);

    $item = $this->getJson('/api/v1/announcements?locale=ru')->json('data.0');

    expect($item['open'])->toBeTrue()
        ->and($item['deadline'])->toBe('бессрочно')
        ->and($item['deadline_at'])->toBeNull()
        ->and($item['deadline_state'])->toBe('unlimited');
});

it('localizes kind and deadline labels', function () {
    $deadline = now()->addWeek();
    Announcement::factory()->published()->create([
        'kind' => AnnouncementKind::Vacancy,
        'deadline' => $deadline,
        'title' => ['ru' => 'Вакансия', 'tg' => 'Ҷойи корӣ', 'en' => 'Vacancy'],
    ]);

    $tg = $this->getJson('/api/v1/announcements?locale=tg')->assertOk()->json('data.0');
    $en = $this->getJson('/api/v1/announcements?locale=en')->assertOk()->json('data.0');

    expect($tg['kind'])->toBe('vacancy')
        ->and($tg['kind_label'])->toBe('Ҷойи корӣ')
        ->and($tg['deadline'])->toBe('то '.$deadline->format('d.m.Y'))
        ->and($en['kind_label'])->toBe('Vacancy')
        ->and($en['deadline'])->toBe('until '.$deadline->format('d.m.Y'));
});

it('filters by kind', function () {
    Announcement::factory()->published()->create(['kind' => AnnouncementKind::Vacancy]);
    Announcement::factory()->published()->create(['kind' => AnnouncementKind::Tender]);

    $this->getJson('/api/v1/announcements?locale=ru&kind=tender')->assertOk()->assertJsonCount(1, 'data');
});

it('serves a public announcement detail by slug and rejects drafts', function () {
    $public = Announcement::factory()->published()->create([
        'application_url' => 'https://jobs.example.tj/apply',
    ]);
    $draft = Announcement::factory()->create();

    $this->getJson("/api/v1/announcements/{$public->slug}?locale=ru")
        ->assertOk()
        ->assertJsonPath('data.slug', $public->slug)
        ->assertJsonPath('data.application_url', 'https://jobs.example.tj/apply');

    $this->getJson("/api/v1/announcements/{$draft->slug}?locale=ru")->assertNotFound();
});

it('omits a material when the requested locale is not published', function () {
    Announcement::factory()->published()->create([
        'title' => ['ru' => 'Русский', 'tg' => 'Тоҷикӣ', 'en' => ''],
    ]);

    expect($this->getJson('/api/v1/announcements?locale=tg')->json('data.0.title'))->toBe('Тоҷикӣ');
    $this->getJson('/api/v1/announcements?locale=en')
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

it('exposes only public fields', function () {
    Announcement::factory()->published()->create();

    $item = $this->getJson('/api/v1/announcements?locale=ru')->json('data.0');

    expect(array_keys($item))->toEqualCanonicalizing([
        'slug', 'kind', 'kind_label', 'title', 'org', 'desc', 'deadline', 'deadline_at', 'deadline_state', 'open', 'application_url',
    ]);
});
