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

    $response = $this->getJson('/api/v1/instructions');

    $response->assertOk()->assertJsonCount(2, 'data');
    expect($response->json('data.0.title'))->toBe('Закреплённая');
});

it('filters to priority instructions', function () {
    Instruction::factory()->published()->create(['is_priority' => true]);
    Instruction::factory()->published()->create(['is_priority' => false]);

    $this->getJson('/api/v1/instructions?priority=1')->assertOk()->assertJsonCount(1, 'data');
});

it('returns localized sections on detail with a per-section ru fallback', function () {
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
        // `during` has no tg → falls back to the ru steps.
        ->and($tg['sections']['during'])->toBe(['Во время РУ']);
});

it('returns 404 for a draft instruction by slug', function () {
    Instruction::factory()->create(['slug' => 'hidden-guide', 'status' => ContentStatus::Draft]);

    $this->getJson('/api/v1/instructions/hidden-guide')->assertNotFound();
});

it('omits sections and internal fields from the list', function () {
    Instruction::factory()->published()->create();

    $item = $this->getJson('/api/v1/instructions')->json('data.0');

    expect(array_keys($item))->toEqualCanonicalizing([
        'slug', 'title', 'summary', 'hazard', 'hazard_label', 'hazard_icon', 'priority', 'image', 'image_srcset',
    ]);
});
