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

    expect($data)->toBeArray()->toHaveCount(6)
        ->and($data[0])->toHaveKeys(['num', 'name', 'desc'])
        ->and($data[0]['num'])->toBe('01')
        ->and($data[5]['num'])->toBe('06');
});

it('resolves unit fields to the requested locale', function () {
    $ru = $this->getJson('/api/v1/structure?locale=ru')->json('data');
    $first = collect($ru)->firstWhere('num', '01');
    expect($first['name'])->toBe('Центр управления в кризисных ситуациях');

    $en = $this->getJson('/api/v1/structure?locale=en')->json('data');
    $firstEn = collect($en)->firstWhere('num', '01');
    expect($firstEn['name'])->toBe('Crisis Management Centre');
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
