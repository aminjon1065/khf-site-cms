<?php

use App\Enums\ContentStatus;
use App\Models\Instruction;
use App\Models\News;
use App\Models\User;
use App\Services\PublicationChecklist;
use App\Services\WorkflowService;
use App\Support\EditorialContent;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\seed;

beforeEach(function () {
    seed(RolePermissionSeeder::class);
});

function previewUser(string $role = 'editor'): User
{
    $user = User::factory()->create();
    giveRole($user, $role);

    return $user;
}

it('serves an authenticated signed preview without cache or indexing', function () {
    $editor = previewUser();
    $news = News::factory()->create([
        'title' => ['ru' => 'Русский fallback', 'tg' => 'Тоҷикӣ', 'en' => ''],
        'body' => ['ru' => '<p>Текст preview</p>', 'tg' => '<p>Матн</p>', 'en' => ''],
    ]);
    $url = app(EditorialContent::class)->previewUrl($news);

    actingAs($editor)
        ->get($url)
        ->assertOk()
        ->assertHeader('Cache-Control', 'max-age=0, no-store, private')
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive')
        ->assertInertia(fn (Assert $page) => $page
            ->component('editorial/preview')
            ->where('preview.locale', 'ru')
            ->where('preview.title', 'Русский fallback')
            ->has('preview.checklist'));
});

it('rejects unsigned and anonymous preview requests', function () {
    $news = News::factory()->create();
    $url = app(EditorialContent::class)->previewUrl($news);

    actingAs(previewUser())
        ->get("/editorial/news/{$news->id}/preview?locale=ru")
        ->assertForbidden();

    auth()->logout();
    $this->get($url)->assertRedirect('/login');
});

it('does not substitute another language when the preview locale has no title', function () {
    $editor = previewUser();
    $news = News::factory()->create([
        'title' => ['ru' => 'Русский заголовок', 'tg' => 'Сарлавҳа', 'en' => ''],
    ]);
    $url = URL::temporarySignedRoute('editorial.preview', now()->addMinute(), [
        'contentType' => 'news',
        'contentId' => $news->id,
        'locale' => 'en',
    ]);

    actingAs($editor)
        ->get($url)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('preview.locale', 'en')
            ->where('preview.title', '')
            ->where('preview.body', '')
            ->where('preview.available', false)
            ->where('preview.missing', 'title')
            ->where('preview.title_word', 'заголовка'));
});

it('does not show a language version that has no text', function () {
    $editor = previewUser();
    $instruction = Instruction::factory()->create([
        'name' => ['ru' => 'Землетрясение', 'tg' => 'Заминҷунбӣ', 'en' => ''],
        'body' => ['ru' => '', 'tg' => '<p>Матн</p>', 'en' => ''],
        'sections' => ['before' => ['ru' => [], 'tg' => ['Қадам']]],
    ]);
    $url = URL::temporarySignedRoute('editorial.preview', now()->addMinute(), [
        'contentType' => 'instructions',
        'contentId' => $instruction->id,
        'locale' => 'ru',
    ]);

    actingAs($editor)
        ->get($url)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('preview.locale', 'ru')
            ->where('preview.available', false)
            ->where('preview.missing', 'text')
            ->where('preview.body', '')
            ->where('preview.text_word', 'ни шагов, ни текста'));

    // Without a requested language the preview opens the one on the site.
    actingAs($editor)
        ->get(app(EditorialContent::class)->previewUrl($instruction))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('preview.locale', 'tg')
            ->where('preview.available', true)
            ->where('preview.missing', null));
});

it('opens the signed preview in the language a material is written in', function () {
    $editor = previewUser();
    $news = News::factory()->create([
        'title' => ['ru' => '', 'tg' => 'Танҳо тоҷикӣ', 'en' => ''],
    ]);

    actingAs($editor)
        ->get(app(EditorialContent::class)->previewUrl($news))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('preview.locale', 'tg')
            ->where('preview.title', 'Танҳо тоҷикӣ')
            ->where('preview.available', true));
});

it('lets a single-language material pass the checklist and reports the missing versions', function () {
    $news = News::factory()->create([
        'title' => ['ru' => '', 'tg' => 'Сарлавҳа', 'en' => ''],
        'summary' => ['ru' => '', 'tg' => 'Хулоса', 'en' => ''],
        'body' => ['ru' => '', 'tg' => '<p>Матн</p>', 'en' => ''],
        'seo' => ['tg' => ['title' => 'Сарлавҳа', 'description' => 'Тавсиф']],
    ]);

    $items = collect(app(PublicationChecklist::class)->inspect($news));

    expect($items->firstWhere('key', 'translation_any'))
        ->toMatchArray(['ok' => true, 'blocking' => true])
        ->and($items->firstWhere('key', 'translation_tg'))
        ->toMatchArray(['ok' => true, 'blocking' => false])
        ->and($items->firstWhere('key', 'translation_ru'))
        ->toMatchArray([
            'ok' => false,
            'blocking' => false,
            'detail' => 'Нет заголовка — на русской версии сайта материал не появится.',
        ]);

    expect(fn () => app(PublicationChecklist::class)->ensurePublishable($news))
        ->not->toThrow(ValidationException::class);
});

it('blocks publication while no language version is complete', function () {
    $news = News::factory()->create([
        'title' => ['ru' => 'Заголовок', 'tg' => '', 'en' => ''],
        'summary' => ['ru' => '', 'tg' => '', 'en' => ''],
        'body' => ['ru' => '<p>Текст</p>', 'tg' => '', 'en' => ''],
    ]);

    $items = collect(app(PublicationChecklist::class)->inspect($news));

    expect($items->firstWhere('key', 'translation_any'))
        ->toMatchArray(['ok' => false, 'blocking' => true])
        // Title and text of three required fields (title, lead, text); the
        // optional search snippet doesn't count.
        ->and($items->firstWhere('key', 'translation_ru')['detail'])
        ->toBe('Заполнена на 67%.');

    expect(fn () => app(PublicationChecklist::class)->ensurePublishable($news))
        ->toThrow(ValidationException::class);
});

it('warns that a language version without its text stays off the site', function () {
    $news = News::factory()->create([
        'title' => ['ru' => 'Заголовок', 'tg' => 'Сарлавҳа', 'en' => ''],
        'body' => ['ru' => '', 'tg' => '<p>Матн</p>', 'en' => ''],
    ]);
    $instruction = Instruction::factory()->create([
        'name' => ['ru' => 'Землетрясение', 'tg' => 'Заминҷунбӣ', 'en' => ''],
        'body' => ['ru' => '', 'tg' => '', 'en' => ''],
        'sections' => ['before' => ['tg' => ['Қадам'], 'ru' => []]],
    ]);

    $newsRu = collect(app(PublicationChecklist::class)->inspect($news))->firstWhere('key', 'translation_ru');
    $instructionRu = collect(app(PublicationChecklist::class)->inspect($instruction))->firstWhere('key', 'translation_ru');

    expect($newsRu['detail'])->toBe('Нет текста — на русской версии сайта материал не появится.')
        ->and($instructionRu['detail'])->toBe('Нет ни шагов, ни текста — на русской версии сайта материал не появится.');
});

it('blocks publication when a cover has no alt text', function () {
    Storage::fake('private');
    Queue::fake();
    $chiefEditor = previewUser('chief_editor');
    $news = News::factory()->create([
        'status' => ContentStatus::Approved,
        'cover_alt' => null,
    ]);
    $media = $news
        ->addMedia(UploadedFile::fake()->image('cover.jpg', 1200, 630))
        ->toMediaCollection('cover');
    $media->setCustomProperty('conversion_status', 'ready')->save();

    $items = app(PublicationChecklist::class)->inspect($news);

    expect(collect($items)->firstWhere('key', 'image_alt'))
        ->toMatchArray(['ok' => false, 'blocking' => true]);

    expect(fn () => app(WorkflowService::class)->transition(
        $news,
        ContentStatus::Published,
        $chiefEditor,
    ))->toThrow(ValidationException::class);
});

it('blocks unsafe links and unfinished media but leaves SEO as a warning', function () {
    Storage::fake('private');
    Queue::fake();
    $news = News::factory()->create([
        'body' => [
            'ru' => '<p><a href="javascript:alert(1)">Плохая ссылка</a></p>',
            'tg' => '<p>Матн</p>',
            'en' => '',
        ],
        'seo' => [],
    ]);
    $media = $news
        ->addMedia(UploadedFile::fake()->image('cover.jpg', 1200, 630))
        ->toMediaCollection('cover');
    $media->setCustomProperty('conversion_status', 'failed')->save();

    $items = collect(app(PublicationChecklist::class)->inspect($news));

    expect($items->firstWhere('key', 'links'))
        ->toMatchArray(['ok' => false, 'blocking' => true])
        ->and($items->firstWhere('key', 'media_ready'))
        ->toMatchArray(['ok' => false, 'blocking' => true])
        ->and($items->firstWhere('key', 'seo'))
        ->toMatchArray(['ok' => false, 'blocking' => false]);
});

it('does not block publication while cover conversions are still queued', function () {
    Storage::fake('content_private');
    Queue::fake();
    $news = News::factory()->create(['cover_alt' => 'Обложка новости']);
    $media = $news
        ->addMedia(UploadedFile::fake()->image('cover.jpg', 1200, 630))
        ->toMediaCollection('cover');
    $media->setCustomProperty('conversion_status', 'pending')->save();

    $subject = $news->fresh()->load('media');
    $items = collect(app(PublicationChecklist::class)->inspect($subject));

    expect($items->firstWhere('key', 'media_ready'))
        ->toMatchArray(['ok' => false, 'blocking' => false]);

    expect(fn () => app(PublicationChecklist::class)->ensurePublishable($subject))
        ->not->toThrow(ValidationException::class);
});

it('wires locale, mobile, desktop, OG and checklist preview into every form', function (string $form) {
    $source = file_get_contents(resource_path("js/pages/{$form}/form.tsx"));

    expect($source)
        ->toContain('preview={{')
        ->toContain('locales:')
        ->toContain('checklist:')
        ->toContain('signedUrl:');
})->with([
    'news',
    'pages',
    'projects',
    'instructions',
    'announcements',
    'documents',
]);

it('does not list an untouched English version, and marks a started one optional', function () {
    $bilingual = News::factory()->create([
        'title' => ['ru' => 'Заголовок', 'tg' => 'Сарлавҳа', 'en' => ''],
        'summary' => ['ru' => 'Лид', 'tg' => 'Лид', 'en' => ''],
        'body' => ['ru' => '<p>Текст</p>', 'tg' => '<p>Матн</p>', 'en' => ''],
    ]);
    $withEnglish = News::factory()->create([
        'title' => ['ru' => 'Заголовок', 'tg' => 'Сарлавҳа', 'en' => 'Title'],
        'summary' => ['ru' => 'Лид', 'tg' => 'Лид', 'en' => ''],
        'body' => ['ru' => '<p>Текст</p>', 'tg' => '<p>Матн</p>', 'en' => ''],
    ]);

    $checklist = app(PublicationChecklist::class);

    expect(collect($checklist->inspect($bilingual))->firstWhere('key', 'translation_en'))->toBeNull()
        ->and(collect($checklist->inspect($withEnglish))->firstWhere('key', 'translation_en'))
        ->toMatchArray([
            'label' => 'Английская версия заполнена (необязательно)',
            'ok' => false,
            'blocking' => false,
        ]);
});
