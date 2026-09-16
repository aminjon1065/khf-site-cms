<?php

use App\Enums\DocType;
use App\Enums\SubmissionStatus;
use App\Models\Document;
use App\Models\Leader;
use App\Models\MediaAsset;
use App\Models\Region;
use App\Models\StructureUnit;
use App\Models\Submission;
use Database\Seeders\DocumentSeeder;
use Database\Seeders\LeaderSeeder;
use Database\Seeders\MediaAssetSeeder;
use Database\Seeders\RegionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\StructureUnitSeeder;
use Database\Seeders\SubmissionSeeder;
use Database\Seeders\UserSeeder;

beforeEach(function (): void {
    config()->set('seeding.source.path', base_path('tests/Fixtures/source'));
    config()->set('seeding.source.media', false);
});

it('seeds exactly one chairman with deputies after him', function (): void {
    $this->seed(LeaderSeeder::class);

    expect(Leader::query()->where('is_chairman', true)->count())->toBe(1)
        ->and(Leader::query()->count())->toBeGreaterThan(1);

    $chairman = Leader::query()->where('is_chairman', true)->sole();

    expect($chairman->sort)->toBe(0)
        ->and(Leader::query()->ordered()->first()?->getKey())->toBe($chairman->getKey());
});

it('gives every leader and unit all three locales', function (): void {
    $this->seed([LeaderSeeder::class, StructureUnitSeeder::class]);

    foreach (Leader::query()->get() as $leader) {
        foreach (['ru', 'tg', 'en'] as $locale) {
            expect($leader->getTranslation('role', $locale, false))->not->toBe('')
                ->and($leader->getTranslation('name', $locale, false))->not->toBe('');
        }
    }

    foreach (StructureUnit::query()->get() as $unit) {
        foreach (['ru', 'tg', 'en'] as $locale) {
            expect($unit->getTranslation('name', $locale, false))->not->toBe('')
                ->and($unit->getTranslation('desc', $locale, false))->not->toBe('');
        }
    }
});

it('numbers structure units uniquely and names the head where the source publishes one', function (): void {
    $this->seed(StructureUnitSeeder::class);

    $units = StructureUnit::query()->ordered()->get();

    expect($units->pluck('num')->unique())->toHaveCount($units->count())
        ->and($units->count())->toBeGreaterThan(20);

    $crisisCentre = StructureUnit::query()->where('name->ru', 'Центр управления в кризисных ситуациях')->sole();

    expect($crisisCentre->getTranslation('desc', 'ru', false))->toContain('Начальник —')
        ->and($crisisCentre->getTranslation('desc', 'tg', false))->toContain('Сардор —');
});

it('seeds regional directorates whose district count matches the districts seeded', function (): void {
    $this->seed(RegionSeeder::class);

    // Псевдоним обязателен: `withCount('districts')` пишет результат в
    // `districts_count`, а это настоящая колонка таблицы — без алиаса
    // проверка сравнивала бы значение само с собой.
    $regions = Region::query()->withCount(['districts as seeded_districts'])->get();

    expect($regions)->toHaveCount(5);

    foreach ($regions as $region) {
        expect($region->districts_count)->toBe((int) $region->seeded_districts)
            ->and($region->seeded_districts)->toBeGreaterThan(0)
            ->and($region->email)->not->toBeNull()
            ->and($region->duty_phone)->not->toBeNull();
    }
});

it('seeds the legal corpus with dated resolutions and undated laws', function (): void {
    $this->seed([RolePermissionSeeder::class, UserSeeder::class, DocumentSeeder::class]);

    $laws = Document::query()->where('doc_type', DocType::Law)->get();
    $resolution = Document::query()->where('number', '№ 833')->sole();

    expect($laws->count())->toBeGreaterThanOrEqual(4)
        // Индекс законов на сайте публикует только названия — дату не выдумываем.
        ->and($laws->pluck('doc_date')->filter())->toBeEmpty()
        ->and($resolution->doc_date?->toDateString())->toBe('2014-12-31')
        ->and($resolution->section)->toBe('Постановления Правительства');
});

it('attaches a downloadable file to every published document, offline included', function (): void {
    $this->seed([RolePermissionSeeder::class, UserSeeder::class, DocumentSeeder::class]);

    $published = Document::query()->public()->get();

    expect($published)->not->toBeEmpty();

    foreach ($published as $document) {
        expect($document->hasAnyFile())->toBeTrue();
    }
});

it('seeds citizen appeals across every status with tracking numbers', function (): void {
    $this->seed([RolePermissionSeeder::class, UserSeeder::class, RegionSeeder::class, SubmissionSeeder::class]);

    $submissions = Submission::query()->get();

    expect($submissions->count())->toBeGreaterThanOrEqual(10)
        ->and($submissions->pluck('status')->unique())->toHaveCount(count(SubmissionStatus::cases()));

    foreach ($submissions as $submission) {
        expect($submission->tracking_number)->toStartWith('КЧС-');
    }

    expect(Submission::query()->has('comments')->count())->toBeGreaterThan(0);
});

it('creates no media assets when source media is switched off', function (): void {
    $this->seed([RolePermissionSeeder::class, UserSeeder::class, MediaAssetSeeder::class]);

    expect(MediaAsset::query()->count())->toBe(0);
});
