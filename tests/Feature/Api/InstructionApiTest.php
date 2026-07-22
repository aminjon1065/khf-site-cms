<?php

use App\Enums\ContentStatus;
use App\Models\Instruction;

it('returns only publicly visible instructions, pinned first', function () {
    Instruction::factory()->published()->create([
        'name' => ['ru' => 'Обычная', 'tg' => '', 'en' => ''], 'is_priority' => false, 'sort' => 5,
    ]);
    Instruction::factory()->published()->create([
        'name' => ['ru' => 'Закреплённая', 'tg' => '', 'en' => ''], 'is_priority' => true, 'sort' => 9,
    ]);
    Instruction::factory()->create(['status' => ContentStatus::Draft]);

    $response = $this->getJson('/api/v1/instructions?locale=ru');

    $response->assertOk()->assertJsonCount(2, 'data');
    expect($response->json('data.0.title'))->toBe('Закреплённая');
});

it('filters to priority instructions', function () {
    Instruction::factory()->published()->create(['is_priority' => true]);
    Instruction::factory()->published()->create(['is_priority' => false]);

    $this->getJson('/api/v1/instructions?locale=ru&priority=1')->assertOk()->assertJsonCount(1, 'data');
});

it('can exclude priority instructions for a separately paginated catalogue', function () {
    Instruction::factory()->published()->create(['is_priority' => true]);
    Instruction::factory()->count(2)->published()->create(['is_priority' => false]);

    $response = $this->getJson('/api/v1/instructions?locale=ru&exclude_priority=1')->assertOk();

    $response->assertJsonCount(2, 'data');
    expect(collect($response->json('data'))->pluck('priority')->unique()->all())->toBe([false]);
});

it('returns localized sections without mixing in another locale', function () {
    Instruction::factory()->published()->create([
        'slug' => 'zemletryasenie-test',
        'name' => ['ru' => 'Землетрясение', 'tg' => 'Заминҷунбӣ', 'en' => ''],
        'sections' => [
            'before' => ['ru' => ['Шаг РУ'], 'tg' => ['Қадами ТҶ']],
            'during' => ['ru' => ['Во время РУ']],
            'after' => ['ru' => ['После РУ']],
            'prohibited' => ['ru' => ['Нельзя РУ']],
        ],
    ]);

    $ru = $this->getJson('/api/v1/instructions/zemletryasenie-test?locale=ru')->json('data');
    expect($ru['sections']['before'])->toBe(['Шаг РУ'])
        ->and($ru['sections']['prohibited'])->toBe(['Нельзя РУ']);

    $tg = $this->getJson('/api/v1/instructions/zemletryasenie-test?locale=tg')->json('data');
    expect($tg['title'])->toBe('Заминҷунбӣ')
        ->and($tg['sections']['before'])->toBe(['Қадами ТҶ'])
        ->and($tg['sections']['during'])->toBe([]);
});

it('returns 404 for a draft instruction by slug', function () {
    Instruction::factory()->create(['slug' => 'hidden-guide', 'status' => ContentStatus::Draft]);

    $this->getJson('/api/v1/instructions/hidden-guide?locale=ru')->assertNotFound();
});

it('omits sections and internal fields from the list', function () {
    Instruction::factory()->published()->create();

    $item = $this->getJson('/api/v1/instructions?locale=ru')->json('data.0');

    expect(array_keys($item))->toEqualCanonicalizing([
        'slug', 'title', 'summary', 'hazard', 'hazard_label', 'hazard_icon', 'priority', 'image', 'image_srcset',
    ]);
});
