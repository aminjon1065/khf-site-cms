<?php

use App\Models\StructureUnit;
use Database\Seeders\StructureUnitSeeder;
use Illuminate\Support\Facades\DB;

use function Pest\Laravel\seed;

beforeEach(function () {
    seed(StructureUnitSeeder::class);
});

it('returns the structure units in configured order', function () {
    $data = $this->getJson('/api/v1/structure?locale=ru')->assertOk()->json('data');
    // Состав подразделений меняется вслед за структурой Комитета, поэтому
    // проверяем сам порядок выдачи, а не конкретную длину списка.
    $expected = StructureUnit::query()->ordered()->pluck('num')->all();

    expect($data)->toBeArray()->toHaveCount(count($expected))
        ->and($data[0])->toHaveKeys(['num', 'name', 'desc'])
        ->and($data[0]['num'])->toBe('01')
        ->and(array_column($data, 'num'))->toBe($expected);
});

it('resolves unit fields to the requested locale', function () {
    $unit = StructureUnit::query()->ordered()->firstOrFail();

    $ru = $this->getJson('/api/v1/structure?locale=ru')->json('data');
    $first = collect($ru)->firstWhere('num', $unit->num);
    expect($first['name'])->toBe($unit->getTranslation('name', 'ru'));

    $en = $this->getJson('/api/v1/structure?locale=en')->json('data');
    $firstEn = collect($en)->firstWhere('num', $unit->num);
    expect($firstEn['name'])->toBe($unit->getTranslation('name', 'en'))
        ->and($firstEn['name'])->not->toBe($first['name']);
});

it('changes the response on the next request after a unit is saved', function () {
    $unit = StructureUnit::query()->where('num', '01')->firstOrFail();

    $this->getJson('/api/v1/structure?locale=ru')->assertOk();

    $unit->setTranslation('name', 'ru', 'Новое название');
    $unit->save();

    $data = $this->getJson('/api/v1/structure?locale=ru')->json('data');
    expect(collect($data)->firstWhere('num', '01')['name'])->toBe('Новое название');
});

it('serves the second identical request from cache without hitting the database', function () {
    $this->getJson('/api/v1/structure?locale=ru')->assertOk();

    $queries = 0;
    DB::listen(function () use (&$queries) {
        $queries++;
    });

    $this->getJson('/api/v1/structure?locale=ru')->assertOk();

    expect($queries)->toBe(0);
});

it('nests subunits under their parent at any depth', function () {
    $directorate = StructureUnit::query()->where('num', '01')->firstOrFail();
    $second = StructureUnit::factory()->childOf($directorate)->create(['num' => '01.2', 'sort' => 2]);
    $first = StructureUnit::factory()->childOf($directorate)->create(['num' => '01.1', 'sort' => 1]);
    StructureUnit::factory()->childOf($first)->create(['num' => '01.1.1']);
    $topLevel = StructureUnit::query()->whereNull('parent_id')->count();

    $data = $this->getJson('/api/v1/structure?locale=ru')->assertOk()->json('data');
    $unit = collect($data)->firstWhere('num', '01');

    expect($data)->toHaveCount($topLevel)
        ->and(array_column($data, 'num'))->not->toContain('01.1')
        ->and(array_column($unit['children'], 'num'))->toBe(['01.1', '01.2'])
        ->and($unit['children'][0]['name'])->toBe($first->getTranslation('name', 'ru'))
        ->and(array_column($unit['children'][0]['children'], 'num'))->toBe(['01.1.1'])
        ->and($unit['children'][1]['children'])->toBe([])
        ->and($second->parent_id)->toBe($directorate->id);
});
