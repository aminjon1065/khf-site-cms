<?php

use App\Enums\ContentStatus;
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
    $user->assignRole($role);

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

it('reports fallback for an unavailable signed preview locale', function () {
    $editor = previewUser();
    $news = News::factory()->create([
        'title' => ['ru' => 'Fallback title', 'tg' => 'Сарлавҳа', 'en' => ''],
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
            ->where('preview.title', 'Fallback title')
            ->where('preview.fallback', true));
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
