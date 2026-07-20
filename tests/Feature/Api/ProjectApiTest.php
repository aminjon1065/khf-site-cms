<?php

use App\Enums\ContentStatus;
use App\Enums\ProjectStatus;
use App\Models\Project;

it('returns only publicly visible projects, in display order', function () {
    Project::factory()->published()->create(['sort' => 2, 'title' => ['ru' => 'Второй', 'tg' => '', 'en' => '']]);
    Project::factory()->published()->create(['sort' => 1, 'title' => ['ru' => 'Первый', 'tg' => '', 'en' => '']]);
    Project::factory()->create(['status' => ContentStatus::Draft]);

    $response = $this->getJson('/api/v1/projects');

    $response->assertOk()->assertJsonCount(2, 'data');
    expect($response->json('data.0.title'))->toBe('Первый');
});

it('filters by lifecycle status', function () {
    Project::factory()->published()->create(['lifecycle_status' => ProjectStatus::Completed]);
    Project::factory()->published()->create(['lifecycle_status' => ProjectStatus::Implementing]);

    $this->getJson('/api/v1/projects?lifecycle=completed')->assertOk()->assertJsonCount(1, 'data');
});

it('exposes the lifecycle label and tone', function () {
    Project::factory()->published()->create(['lifecycle_status' => ProjectStatus::Implementing]);

    $item = $this->getJson('/api/v1/projects')->json('data.0');

    expect($item['status'])->toBe('Реализуется')
        ->and($item['status_tone'])->toBe('success');
});

it('returns detail with goals/timeline/direction and a ru fallback', function () {
    Project::factory()->published()->create([
        'slug' => 'proj-x',
        'title' => ['ru' => 'Проект X', 'tg' => 'Лоиҳаи X', 'en' => ''],
        'goals' => ['ru' => ['Цель РУ'], 'tg' => []],
        'timeline' => [['date' => '2026', 'text' => 'Этап', 'tone' => 'success']],
        'direction' => ['address' => 'Душанбе', 'phone' => '112', 'email' => 'x@khf.tj'],
    ]);

    $ru = $this->getJson('/api/v1/projects/proj-x?locale=ru')->json('data');
    expect($ru['goals'])->toBe(['Цель РУ'])
        ->and($ru['timeline'])->toHaveCount(1)
        ->and($ru['direction']['email'])->toBe('x@khf.tj');

    $tg = $this->getJson('/api/v1/projects/proj-x?locale=tg')->json('data');
    expect($tg['title'])->toBe('Лоиҳаи X')
        ->and($tg['goals'])->toBe(['Цель РУ']); // tg goals empty → ru fallback
});

it('returns 404 for a draft project by slug', function () {
    Project::factory()->create(['slug' => 'hidden-proj', 'status' => ContentStatus::Draft]);

    $this->getJson('/api/v1/projects/hidden-proj')->assertNotFound();
});

it('omits detail fields from the list', function () {
    Project::factory()->published()->create();

    $item = $this->getJson('/api/v1/projects')->json('data.0');

    expect(array_keys($item))->toEqualCanonicalizing([
        'slug', 'title', 'status', 'status_tone', 'years', 'partner', 'budget', 'desc', 'image', 'image_srcset',
    ]);
});
