<?php

use App\Enums\AnnouncementKind;
use App\Enums\ContentStatus;
use App\Models\Announcement;
use App\Models\News;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SourceAnnouncementSeeder;
use Database\Seeders\SourceNewsSeeder;
use Database\Seeders\TaxonomySeeder;
use Database\Seeders\UserSeeder;

beforeEach(function (): void {
    // Точечный набор фикстур вместо боевого: тест проверяет правила импорта,
    // а не объём выгрузки, и не должен зависеть от очередного скрейпа.
    config()->set('seeding.source.path', base_path('tests/Fixtures/source'));
    config()->set('seeding.source.media', false);
});

it('imports source news, pairing the two sites by their shared cover image', function (): void {
    $this->seed([RolePermissionSeeder::class, UserSeeder::class, TaxonomySeeder::class, SourceNewsSeeder::class]);

    // 2 русских + 2 таджикских, из которых одна пара — один и тот же материал.
    expect(News::query()->count())->toBe(3);

    $paired = News::query()->where('title->ru', 'like', 'КЧС Хатлон:%')->sole();

    expect($paired->getTranslation('title', 'tg', false))
        ->toBe('КҲФ-Хатлон: Наҷотдиҳандагон 3 шаҳрвандро аз ғарқшавӣ наҷот доданд')
        ->and($paired->getTranslation('body', 'ru', false))->toContain('<strong>')
        // Счётчик просмотров — больший из двух сайтов.
        ->and($paired->views_count)->toBe(1779);
});

it('leaves a single-language item without a translation, as the public locale contract expects', function (): void {
    $this->seed([RolePermissionSeeder::class, UserSeeder::class, TaxonomySeeder::class, SourceNewsSeeder::class]);

    $tajikOnly = News::query()->where('title->tg', 'like', 'КҲФ: Огоҳӣ%')->sole();

    expect($tajikOnly->getTranslation('title', 'ru', false))->toBe('')
        ->and($tajikOnly->getTranslation('title', 'tg', false))->not->toBe('');
});

it('files imported news into editorial categories derived from the headline', function (): void {
    $this->seed([RolePermissionSeeder::class, UserSeeder::class, TaxonomySeeder::class, SourceNewsSeeder::class]);

    $categoryOf = fn (News $news): ?string => $news->category?->getTranslation('name', 'ru', false);

    expect($categoryOf(News::query()->where('title->ru', 'like', 'КЧС Хатлон:%')->sole()))
        ->toBe('Спасательные операции')
        ->and($categoryOf(News::query()->where('title->ru', 'like', '%встреча министров%')->sole()))
        ->toBe('Сотрудничество')
        ->and($categoryOf(News::query()->where('title->tg', 'like', 'КҲФ: Огоҳӣ%')->sole()))
        ->toBe('Предупреждения');
});

it('publishes most imported news and keeps the editorial queues non-empty', function (): void {
    $this->seed([RolePermissionSeeder::class, UserSeeder::class, TaxonomySeeder::class, SourceNewsSeeder::class]);

    $published = News::query()->where('status', ContentStatus::Published)->get();

    expect($published)->not->toBeEmpty();

    foreach ($published as $news) {
        expect($news->published_at)->not->toBeNull()
            ->and($news->author_id)->not->toBeNull()
            ->and($news->slug)->not->toBeNull();
    }
});

it('re-seeds without duplicating imported material', function (): void {
    $this->seed([RolePermissionSeeder::class, UserSeeder::class, TaxonomySeeder::class, SourceNewsSeeder::class]);
    $first = News::query()->pluck('slug')->sort()->values();

    $this->seed(SourceNewsSeeder::class);

    expect(News::query()->pluck('slug')->sort()->values()->all())->toBe($first->all());
});

it('imports announcements and tells a procurement notice from a staffing competition', function (): void {
    $this->seed([RolePermissionSeeder::class, UserSeeder::class, SourceAnnouncementSeeder::class]);

    expect(Announcement::query()->count())->toBe(3);

    $tender = Announcement::query()->where('title->ru', 'Запрос на подачу ценовых предложений')->sole();
    $vacancy = Announcement::query()->where('title->ru', 'like', '%вакантных должностей')->sole();
    $tajikVacancy = Announcement::query()->where('title->tg', 'like', 'Эълони озмун%')->sole();

    expect($tender->kind)->toBe(AnnouncementKind::Tender)
        ->and($tender->org)->toContain('Повышение готовности и устойчивости')
        ->and($vacancy->kind)->toBe(AnnouncementKind::Vacancy)
        ->and($tajikVacancy->kind)->toBe(AnnouncementKind::Vacancy)
        // Срок подачи в источнике живёт в тексте объявления, поэтому CMS
        // считает его от даты публикации — он всегда должен быть заполнен.
        ->and($tender->deadline?->toDateString())->toBe('2024-07-11');
});

it('attaches no cover while source media is off, cached file or not', function (): void {
    // Фикстуры ссылаются на настоящие картинки источника, и на машине
    // разработчика они вполне могут лежать в кэше загрузок. Выключенное
    // `seeding.source.media` обязано отсекать и кэш тоже — иначе результат
    // сидинга зависел бы от того, качал ли кто-то эти файлы раньше.
    $this->seed([RolePermissionSeeder::class, UserSeeder::class, TaxonomySeeder::class, SourceNewsSeeder::class]);

    foreach (News::query()->get() as $news) {
        expect($news->hasMedia('cover'))->toBeFalse();
    }
});

it('seeds nothing when the fixtures are missing, instead of failing', function (): void {
    config()->set('seeding.source.path', base_path('tests/Fixtures/source-missing'));

    $this->seed([RolePermissionSeeder::class, UserSeeder::class, TaxonomySeeder::class, SourceNewsSeeder::class]);

    expect(News::query()->count())->toBe(0);
});
