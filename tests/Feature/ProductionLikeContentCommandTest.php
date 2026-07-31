<?php

use App\Models\MediaAsset;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

it('creates an isolated deterministic production-like dataset idempotently', function () {
    Storage::fake('benchmark');

    $arguments = [
        '--news' => 12,
        '--media' => 4,
        '--media-bytes' => 4096,
    ];

    $this->artisan('benchmark:seed', $arguments)
        ->expectsOutputToContain('Benchmark ready: 12 news, 4 media, 4096 storage bytes')
        ->assertSuccessful();
    $this->artisan('benchmark:seed', $arguments)->assertSuccessful();

    $news = DB::table('news')->where('slug', 'like', 'benchmark-news-%');
    $assets = DB::table('media_assets')->where('title', 'like', 'Benchmark media %');
    $media = DB::table('media')
        ->where('model_type', MediaAsset::class)
        ->where('disk', 'benchmark');

    expect($news->count())->toBe(12)
        ->and($assets->count())->toBe(4)
        ->and($media->count())->toBe(4)
        ->and((int) $media->sum('size'))->toBe(4096)
        ->and(json_decode((string) $media->first()->custom_properties, true))->toMatchArray([
            'width' => 8000,
            'height' => 8000,
            'benchmark' => true,
        ]);

    Storage::disk('benchmark')->assertExists('benchmark-manifest.json');

    foreach ($media->get(['id', 'file_name', 'size']) as $item) {
        $path = "{$item->id}/{$item->file_name}";
        Storage::disk('benchmark')->assertExists($path);
        expect(Storage::disk('benchmark')->size($path))->toBe((int) $item->size);
    }
});

it('refuses unsafe environments and database names', function () {
    app()->detectEnvironment(fn (): string => 'production');
    config()->set('database.connections.sqlite.database', '/tmp/khf-production.sqlite');

    $this->artisan('benchmark:seed', [
        '--news' => 1,
        '--media' => 1,
        '--media-bytes' => 512,
    ])
        ->expectsOutputToContain('Refusing to seed')
        ->assertFailed();

    expect(DB::table('news')->where('slug', 'like', 'benchmark-news-%')->count())->toBe(0);
});

it('validates bounded benchmark profile sizes', function () {
    $this->artisan('benchmark:seed', [
        '--news' => 0,
        '--media' => 1,
        '--media-bytes' => 511,
    ])
        ->expectsOutputToContain('Invalid profile')
        ->assertExitCode(2);
});
