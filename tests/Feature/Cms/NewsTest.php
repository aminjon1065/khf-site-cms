<?php

use App\Enums\ContentStatus;
use App\Models\News;
use App\Models\Region;
use App\Models\User;
use Database\Seeders\RegionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\seed;

beforeEach(function () {
    seed([RolePermissionSeeder::class, RegionSeeder::class]);
});

function newsUser(string $role): User
{
    $user = User::factory()->create([
        'region_id' => $role === 'regional_editor' ? Region::query()->value('id') : null,
    ]);
    $user->assignRole($role);

    return $user;
}

it('lets an editor open the news create form', function () {
    actingAs(newsUser('editor'))->get('/news/create')->assertOk();
});

it('forbids a viewer from opening the create form', function () {
    actingAs(newsUser('viewer'))->get('/news/create')->assertForbidden();
});

it('creates a draft with an auto-generated slug', function () {
    actingAs(newsUser('editor'))->post('/news', [
        'title' => ['ru' => 'Учения в Хатлонской области', 'tg' => '', 'en' => ''],
        'seo' => [
            'ru' => ['title' => 'Учения КЧС', 'description' => 'Описание учений.'],
            'tg' => ['title' => '', 'description' => ''],
            'en' => ['title' => '', 'description' => ''],
        ],
        'action' => 'draft',
    ])->assertRedirect('/news');

    $news = News::query()->first();

    expect($news)->not->toBeNull()
        ->and($news->status)->toBe(ContentStatus::Draft)
        ->and($news->slug)->not->toBeEmpty()
        ->and(data_get($news->seo, 'ru.title'))->toBe('Учения КЧС');
});

it('generates a unique slug when titles collide', function () {
    $editor = newsUser('editor');
    $payload = fn () => [
        'title' => ['ru' => 'Одинаковый заголовок', 'tg' => '', 'en' => ''],
        'action' => 'draft',
    ];

    actingAs($editor)->post('/news', $payload());
    actingAs($editor)->post('/news', $payload());

    $slugs = News::query()->pluck('slug');

    expect($slugs)->toHaveCount(2)
        ->and($slugs->unique())->toHaveCount(2);
});

it('requires a russian title', function () {
    actingAs(newsUser('editor'))->post('/news', [
        'title' => ['ru' => '', 'tg' => 'Ягон чиз'],
        'action' => 'draft',
    ])->assertSessionHasErrors('title.ru');
});

it('sends news to review when an editor submits for approval', function () {
    actingAs(newsUser('editor'))->post('/news', [
        'title' => ['ru' => 'Материал на согласование', 'tg' => '', 'en' => ''],
        'action' => 'submit',
        'publish_mode' => 'review',
    ])->assertRedirect('/news');

    expect(News::query()->first()->status)->toBe(ContentStatus::Review);
});

it('requires a future publication time when scheduling news', function () {
    $editor = newsUser('editor');
    $payload = [
        'title' => ['ru' => 'Запланированная новость', 'tg' => '', 'en' => ''],
        'action' => 'submit',
        'publish_mode' => 'schedule',
    ];

    actingAs($editor)->post('/news', $payload)
        ->assertSessionHasErrors('scheduled_at');

    actingAs($editor)->post('/news', [
        ...$payload,
        'scheduled_at' => now()->subMinute()->toDateTimeString(),
    ])->assertSessionHasErrors('scheduled_at');

    actingAs($editor)->post('/news', [
        ...$payload,
        'scheduled_at' => now()->addHour()->toDateTimeString(),
    ])->assertRedirect('/news');

    expect(News::query()->first()->status)->toBe(ContentStatus::Scheduled);
});

it('lets a chief editor publish immediately', function () {
    actingAs(newsUser('chief_editor'))->post('/news', [
        'title' => ['ru' => 'Срочная публикация', 'tg' => 'Нашри фаврӣ', 'en' => ''],
        'summary' => ['ru' => 'Краткое описание.', 'tg' => 'Тавсифи кӯтоҳ.', 'en' => ''],
        'body' => ['ru' => '<p>Текст публикации.</p>', 'tg' => '<p>Матни нашр.</p>', 'en' => ''],
        'seo' => [
            'ru' => ['title' => 'Срочная публикация', 'description' => 'Краткое описание.'],
            'tg' => ['title' => 'Нашри фаврӣ', 'description' => 'Тавсифи кӯтоҳ.'],
            'en' => ['title' => '', 'description' => ''],
        ],
        'action' => 'submit',
        'publish_mode' => 'now',
    ])->assertRedirect('/news');

    $news = News::query()->first();

    expect($news->status)->toBe(ContentStatus::Published)
        ->and($news->published_at)->not->toBeNull();
});

it('downgrades a publish attempt to review when the user cannot publish', function () {
    // regional_editor may create/edit news but has no news.publish permission.
    actingAs(newsUser('regional_editor'))->post('/news', [
        'title' => ['ru' => 'Попытка публикации', 'tg' => '', 'en' => ''],
        'action' => 'submit',
        'publish_mode' => 'now',
    ])->assertRedirect('/news');

    expect(News::query()->first()->status)->toBe(ContentStatus::Review);
});

it('limits a regional editor to news they authored', function () {
    $regional = newsUser('regional_editor');
    $own = News::factory()->create(['author_id' => $regional->id]);
    $foreign = News::factory()->create(['author_id' => newsUser('editor')->id]);

    actingAs($regional)->get('/news')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('news', 1)
            ->where('news.0.id', $own->id));

    actingAs($regional)->put("/news/{$foreign->id}", [
        'title' => ['ru' => 'Чужой материал'],
        'action' => 'draft',
    ])->assertForbidden();
});

it('publishes via the publish endpoint and the item becomes public', function () {
    $news = News::factory()->create([
        'slug' => 'api-visible',
        'title' => ['ru' => 'Виден в публичном API', 'tg' => 'Дар API намоён аст', 'en' => ''],
    ]);

    actingAs(newsUser('chief_editor'))->post("/news/{$news->id}/publish")->assertRedirect();

    expect($news->fresh()->status)->toBe(ContentStatus::Published);

    $this->getJson('/api/v1/news/api-visible?locale=ru')
        ->assertOk()
        ->assertJsonPath('data.title', 'Виден в публичном API');
});

it('unpublishes a published item to the archive', function () {
    $news = News::factory()->published()->create();

    actingAs(newsUser('chief_editor'))
        ->post("/news/{$news->id}/unpublish", ['comment' => 'Материал устарел'])
        ->assertRedirect();

    expect($news->fresh()->status)->toBe(ContentStatus::Archived);
});

it('forbids a viewer from deleting news', function () {
    $news = News::factory()->create();

    actingAs(newsUser('viewer'))->delete("/news/{$news->id}")->assertForbidden();
});

it('soft-deletes news for an authorized user', function () {
    $news = News::factory()->create();

    actingAs(newsUser('chief_editor'))->delete("/news/{$news->id}")->assertRedirect();

    expect(News::query()->find($news->id))->toBeNull()
        ->and(News::withTrashed()->find($news->id))->not->toBeNull();
});

it('sanitises the rich-text body: keeps formatting, strips scripts and unsafe attributes', function () {
    actingAs(newsUser('chief_editor'))->post('/news', [
        'title' => ['ru' => 'Материал с форматированием', 'tg' => '', 'en' => ''],
        'body' => [
            'ru' => '<h2>Заголовок</h2>'
                .'<p style="text-align:center;color:red">Текст <b>жирный</b><script>alert(1)</script></p>'
                .'<a href="https://khf.tj" onclick="steal()">ссылка</a>'
                .'<img src="/storage/1/a.jpg" alt="фото" onerror="hack()">'
                .'<iframe src="https://evil.example"></iframe>',
            'tg' => '',
            'en' => '',
        ],
        'action' => 'draft',
    ])->assertRedirect('/news');

    $body = News::query()->first()->getTranslation('body', 'ru');

    expect($body)
        ->toContain('<h2>Заголовок</h2>')
        ->toContain('<b>жирный</b>')
        ->toContain('text-align:center') // разрешённое выравнивание сохранено
        ->not->toContain('<script')      // скрипт вырезан
        ->not->toContain('onclick')      // обработчики событий вырезаны
        ->not->toContain('onerror')
        ->not->toContain('<iframe')      // iframe запрещён
        ->not->toContain('color:red');   // недопустимое CSS-свойство убрано
});

it('drops an empty rich-text body instead of storing empty markup', function () {
    actingAs(newsUser('editor'))->post('/news', [
        'title' => ['ru' => 'Без текста', 'tg' => '', 'en' => ''],
        'body' => ['ru' => '<p></p>', 'tg' => '', 'en' => ''],
        'action' => 'draft',
    ])->assertRedirect('/news');

    expect(News::query()->first()->getTranslation('body', 'ru'))->toBeEmpty();
});

it('allows text colour and YouTube embeds but strips other CSS and unsafe iframes', function () {
    actingAs(newsUser('editor'))->post('/news', [
        'title' => ['ru' => 'Цвет и видео', 'tg' => '', 'en' => ''],
        'body' => [
            'ru' => '<p><span style="color:#b3362a">важно</span> '
                .'<span style="color:red;font-size:40px">текст</span></p>'
                .'<div data-youtube-video><iframe src="https://www.youtube.com/embed/dQw4w9WgXcQ" '
                .'width="640" height="360" allowfullscreen></iframe></div>'
                .'<iframe src="https://evil.example/x"></iframe>',
            'tg' => '',
            'en' => '',
        ],
        'action' => 'draft',
    ])->assertRedirect('/news');

    $body = News::query()->first()->getTranslation('body', 'ru');

    expect($body)
        ->toContain('<span style="color:')                  // цвет текста разрешён
        ->toContain('youtube.com/embed/dQw4w9WgXcQ')        // безопасный YouTube-эмбед сохранён
        ->not->toContain('font-size')                       // прочий inline-CSS отброшен
        ->not->toContain('evil.example');                   // чужой iframe вырезан
});

it('stays on the editor when saving a draft with the stay flag (Ctrl+S)', function () {
    $response = actingAs(newsUser('editor'))->post('/news', [
        'title' => ['ru' => 'Черновик со stay', 'tg' => '', 'en' => ''],
        'action' => 'draft',
        'stay' => true,
    ]);

    $news = News::query()->firstOrFail();
    $response->assertRedirect("/news/{$news->id}/edit");
});

it('keeps image figure, caption and align/size classes when sanitising', function () {
    actingAs(newsUser('editor'))->post('/news', [
        'title' => ['ru' => 'Картинка с подписью', 'tg' => '', 'en' => ''],
        'body' => [
            'ru' => '<figure class="re-figure align-center size-medium">'
                .'<img src="/storage/1/a.jpg" class="re-img" alt="фото">'
                .'<figcaption>Подпись к фото</figcaption></figure>'
                .'<img src="/storage/2/b.jpg" class="re-img align-right size-small" alt="карт" onerror="x()">',
            'tg' => '',
            'en' => '',
        ],
        'action' => 'draft',
    ])->assertRedirect('/news');

    $body = News::query()->first()->getTranslation('body', 'ru');

    expect($body)
        ->toContain('<figure class="re-figure align-center size-medium">') // figure + классы
        ->toContain('<figcaption>Подпись к фото</figcaption>')             // подпись сохранена
        ->toContain('align-right size-small')                             // классы на bare-img
        ->not->toContain('onerror');                                      // обработчик вырезан
});

it('preserves image srcset and sizes for responsive images', function () {
    actingAs(newsUser('editor'))->post('/news', [
        'title' => ['ru' => 'Адаптивная картинка', 'tg' => '', 'en' => ''],
        'body' => [
            'ru' => '<img src="/storage/1/x.jpg" '
                .'srcset="/storage/1/conversions/x-sm.jpg 480w, /storage/1/conversions/x-md.jpg 960w" '
                .'sizes="(max-width: 920px) 50vw, 360px" class="re-img size-medium" alt="ф">',
            'tg' => '',
            'en' => '',
        ],
        'action' => 'draft',
    ])->assertRedirect('/news');

    $body = News::query()->first()->getTranslation('body', 'ru');

    expect($body)
        ->toContain('srcset="/storage/1/conversions/x-sm.jpg 480w')
        ->toContain('sizes=')
        ->toContain('size-medium');
});
