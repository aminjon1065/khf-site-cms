<?php

use App\Models\Leader;
use Database\Seeders\LeaderSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\seed;

beforeEach(function () {
    seed(LeaderSeeder::class);
});

it('returns the leadership roster with the chairman first', function () {
    $data = $this->getJson('/api/v1/leadership?locale=ru')->assertOk()->json('data');

    expect($data)->toBeArray()->toHaveCount(4)
        ->and($data[0])->toHaveKeys(['id', 'role', 'name', 'meta', 'bio', 'is_chairman', 'photo_url'])
        ->and($data[0]['is_chairman'])->toBeTrue()
        ->and($data[1]['is_chairman'])->toBeFalse();
});

it('resolves roster fields to the requested locale', function () {
    $ru = $this->getJson('/api/v1/leadership?locale=ru')->json('data');
    $chairman = collect($ru)->firstWhere('is_chairman', true);

    expect($chairman['role'])->toBe('Председатель Комитета')
        ->and($chairman['name'])->toBe('Рустам Назарзода');

    $en = $this->getJson('/api/v1/leadership?locale=en')->json('data');
    $chairmanEn = collect($en)->firstWhere('is_chairman', true);

    expect($chairmanEn['role'])->toBe('Chairman of the Committee')
        ->and($chairmanEn['name'])->toBe('Rustam Nazarzoda');
});

it('changes the response on the next request after a leader is saved', function () {
    $chairman = Leader::query()->where('is_chairman', true)->firstOrFail();

    $this->getJson('/api/v1/leadership?locale=ru')
        ->assertOk()
        ->assertJsonFragment(['name' => 'Рустам Назарзода']);

    $chairman->setTranslation('name', 'ru', 'Новое Имя');
    $chairman->save();

    $this->getJson('/api/v1/leadership?locale=ru')
        ->assertOk()
        ->assertJsonFragment(['name' => 'Новое Имя']);
});

it('serves the second identical request from cache without hitting the database', function () {
    $this->getJson('/api/v1/leadership?locale=ru')->assertOk();

    $queries = 0;
    DB::listen(function () use (&$queries) {
        $queries++;
    });

    $this->getJson('/api/v1/leadership?locale=ru')->assertOk();

    expect($queries)->toBe(0);
});

it('orders deputies by their configured sort after the chairman', function () {
    $data = $this->getJson('/api/v1/leadership?locale=ru')->json('data');
    $deputies = array_slice($data, 1);

    expect($deputies)->toHaveCount(3)
        ->and($deputies[0]['name'])->toBe('Первый заместитель')
        ->and($deputies[1]['name'])->toBe('Заместитель по гражданской обороне')
        ->and($deputies[2]['name'])->toBe('Заместитель по предупреждению ЧС');
});

it('reports a null photo_url until a photo is uploaded', function () {
    Storage::fake('public');
    $chairman = Leader::query()->where('is_chairman', true)->firstOrFail();

    $data = $this->getJson('/api/v1/leadership?locale=ru')->json('data');
    expect(collect($data)->firstWhere('is_chairman', true)['photo_url'])->toBeNull();

    $chairman->addMedia(UploadedFile::fake()->image('chairman.jpg'))->toMediaCollection('photo');
    // Attaching media doesn't fire Leader's own saved event — touch() is what
    // LeaderController::syncMedia() also calls to flush the response cache.
    $chairman->touch();

    $data = $this->getJson('/api/v1/leadership?locale=ru')->json('data');
    expect(collect($data)->firstWhere('is_chairman', true)['photo_url'])->not->toBeNull();
});
