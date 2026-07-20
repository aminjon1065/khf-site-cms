<?php

use App\Models\HomeBlock;
use App\Models\News;
use Database\Seeders\HomeBlockSeeder;
use Database\Seeders\RegionSeeder;

use function Pest\Laravel\seed;

beforeEach(function () {
    seed([RegionSeeder::class, HomeBlockSeeder::class]);
});

it('returns enabled blocks in editor order', function () {
    $data = $this->getJson('/api/v1/home')->assertOk()->json('data');

    $types = collect($data['blocks'])->pluck('type');

    expect($types)->toContain('active_alerts', 'latest_news', 'documents')
        ->and($types)->not->toContain('emergency_contacts') // disabled in seed
        ->and($data['blocks'][0]['type'])->toBe('active_alerts'); // first by sort
});

it('respects a block limit for news', function () {
    News::factory()->count(8)->published()->create();

    $block = HomeBlock::query()->where('type', 'latest_news')->first();
    $block->config = ['limit' => 2];
    $block->save();

    $data = $this->getJson('/api/v1/home')->json('data');

    expect($data['news'])->toHaveCount(2);
});

it('omits a disabled block from the blocks list', function () {
    $block = HomeBlock::query()->where('type', 'documents')->first();
    $block->enabled = false;
    $block->save();

    $types = collect($this->getJson('/api/v1/home')->json('data.blocks'))->pluck('type');

    expect($types)->not->toContain('documents');
});

it('includes the alert snapshot with every region', function () {
    $data = $this->getJson('/api/v1/home')->json('data');

    expect($data['alerts'])->toHaveKeys(['state', 'count', 'regions', 'items'])
        ->and($data['alerts']['regions'])->toHaveCount(5);
});
