<?php

use App\Models\Announcement;
use App\Models\Document;
use App\Models\Instruction;
use App\Models\News;
use App\Models\Page;
use App\Models\Project;
use App\Models\Submission;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

/**
 * `DatabaseSeeder` runs with {@see WithoutModelEvents},
 * so anything a model fills in from a `saving`/`created` hook — a slug, a
 * tracking number — is NOT filled in during a full `db:seed`. Every seeder has
 * to set those itself, and this suite is what keeps that honest: it exercises
 * the real entry point developers use, not one seeder at a time.
 */
beforeEach(function (): void {
    config()->set('seeding.source.path', base_path('tests/Fixtures/source'));
    config()->set('seeding.source.media', false);

    $this->seed(DatabaseSeeder::class);
});

it('gives every seeded record the identifier its public URL is built from', function (): void {
    expect(News::query()->whereNull('slug')->count())->toBe(0)
        ->and(News::query()->where('slug', '')->count())->toBe(0)
        ->and(Announcement::query()->whereNull('slug')->count())->toBe(0)
        ->and(Instruction::query()->whereNull('slug')->count())->toBe(0)
        ->and(Project::query()->whereNull('slug')->count())->toBe(0)
        ->and(Page::query()->whereNull('slug')->count())->toBe(0);
});

it('gives every seeded appeal a tracking number', function (): void {
    expect(Submission::query()->count())->toBeGreaterThan(0)
        ->and(Submission::query()->whereNull('tracking_number')->count())->toBe(0);
});

it('leaves every module with something to show', function (): void {
    expect(News::query()->public()->count())->toBeGreaterThan(0)
        ->and(Announcement::query()->public()->count())->toBeGreaterThan(0)
        ->and(Document::query()->public()->count())->toBeGreaterThan(0)
        ->and(Instruction::query()->public()->count())->toBeGreaterThan(0)
        ->and(Project::query()->public()->count())->toBeGreaterThan(0)
        ->and(Page::query()->public()->count())->toBeGreaterThan(0);
});

it('serves the seeded material through the public API in both site languages', function (): void {
    foreach (['ru', 'tg'] as $locale) {
        $response = $this->withHeader('Accept-Language', $locale)
            ->getJson('/api/v1/news?per_page=5')
            ->assertOk();

        expect($response->json('meta.total'))->toBeGreaterThan(0);

        foreach ($response->json('data') as $item) {
            expect($item['slug'])->toBeString()->not->toBe('')
                ->and($item['title'])->toBeString()->not->toBe('');
        }
    }
});

it('re-seeds on top of itself without duplicating anything', function (): void {
    $before = [
        'news' => News::query()->count(),
        'announcements' => Announcement::query()->count(),
        'documents' => Document::query()->count(),
        'submissions' => Submission::query()->count(),
    ];

    $this->seed(DatabaseSeeder::class);

    expect([
        'news' => News::query()->count(),
        'announcements' => Announcement::query()->count(),
        'documents' => Document::query()->count(),
        'submissions' => Submission::query()->count(),
    ])->toBe($before);
});
