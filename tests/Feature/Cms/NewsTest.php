<?php

use App\Enums\ContentStatus;
use App\Models\News;
use App\Models\PendingChange;
use App\Models\Region;
use App\Models\User;
use App\Support\Slug;
use Database\Seeders\RegionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\seed;

beforeEach(function () {
    seed([RolePermissionSeeder::class, RegionSeeder::class]);
});

function newsUser(string $role): User
{
    // Staff of a regional department: their accounts are limited to it.
    $regional = $role === 'regional_editor';

    return giveRole(User::factory()->create([
        'region_id' => $regional ? Region::query()->value('id') : null,
        'limited_to_region' => $regional,
    ]), $role);
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

it('refuses a typed address longer than the site can hold', function () {
    actingAs(newsUser('editor'))->post('/news', [
        'title' => ['ru' => 'Учения', 'tg' => '', 'en' => ''],
        'slug' => str_repeat('a', Slug::MAX_LENGTH + 1),
        'action' => 'draft',
    ])->assertSessionHasErrors('slug');

    expect(News::query()->count())->toBe(0);
});

it('requires a title in at least one language', function () {
    actingAs(newsUser('editor'))->post('/news', [
        'title' => ['ru' => '', 'tg' => '', 'en' => ''],
        'action' => 'draft',
    ])->assertSessionHasErrors(['title' => 'Укажите заголовок новости хотя бы на одном языке.']);
});

it('saves news written in a single language and derives the slug from that title', function () {
    actingAs(newsUser('editor'))->post('/news', [
        'title' => ['ru' => '', 'tg' => '', 'en' => 'Rescue drill in Khatlon'],
        'action' => 'draft',
    ])->assertSessionHasNoErrors();

    expect(News::query()->sole()->slug)->toBe('rescue-drill-in-khatlon');
});

it('lists a single-language news item under the title it has', function () {
    News::factory()->create(['title' => ['ru' => '', 'tg' => 'Танҳо бо забони тоҷикӣ', 'en' => '']]);

    actingAs(newsUser('editor'))->get('/news')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('news.0.title', 'Танҳо бо забони тоҷикӣ'));
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

it('lets a chief editor publish news written only in Tajik', function () {
    actingAs(newsUser('chief_editor'))->post('/news', [
        'title' => ['ru' => '', 'tg' => 'Нашри фаврӣ', 'en' => ''],
        'summary' => ['ru' => '', 'tg' => 'Тавсифи кӯтоҳ.', 'en' => ''],
        'body' => ['ru' => '', 'tg' => '<p>Матни нашр.</p>', 'en' => ''],
        'seo' => [
            'ru' => ['title' => '', 'description' => ''],
            'tg' => ['title' => 'Нашри фаврӣ', 'description' => 'Тавсифи кӯтоҳ.'],
            'en' => ['title' => '', 'description' => ''],
        ],
        'action' => 'submit',
        'publish_mode' => 'now',
    ])->assertRedirect('/news')->assertSessionHasNoErrors();

    expect(News::query()->sole()->status)->toBe(ContentStatus::Published);
});

it('publishes news even when cover conversions are still queued', function () {
    Storage::fake('content_private');
    Queue::fake();
    $news = News::factory()->create(['cover_alt' => 'Обложка новости']);
    $media = $news
        ->addMedia(UploadedFile::fake()->image('cover.jpg', 1200, 630))
        ->toMediaCollection('cover');
    $media->setCustomProperty('conversion_status', 'pending')->save();

    actingAs(newsUser('chief_editor'))
        ->post("/news/{$news->id}/publish")
        ->assertRedirect();

    expect($news->fresh()->status)->toBe(ContentStatus::Published)
        ->and($news->fresh()->published_at)->not->toBeNull();
});

it('still blocks publication when cover conversion failed', function () {
    Storage::fake('content_private');
    Queue::fake();
    $news = News::factory()->create(['cover_alt' => 'Обложка новости']);
    $media = $news
        ->addMedia(UploadedFile::fake()->image('cover.jpg', 1200, 630))
        ->toMediaCollection('cover');
    $media->setCustomProperty('conversion_status', 'failed')->save();

    actingAs(newsUser('chief_editor'))
        ->post("/news/{$news->id}/publish")
        ->assertSessionHasErrors('publication_checklist');

    expect($news->fresh()->status)->toBe(ContentStatus::Draft);
});

it('keeps a newly created news when immediate publish is blocked', function () {
    $response = actingAs(newsUser('chief_editor'))->post('/news', [
        'title' => ['ru' => 'Только русский заголовок', 'tg' => '', 'en' => ''],
        'action' => 'submit',
        'publish_mode' => 'now',
    ]);

    $news = News::query()->first();

    expect($news)->not->toBeNull()
        ->and($news->status)->toBe(ContentStatus::Draft);

    $response
        ->assertRedirect("/news/{$news->id}/edit")
        ->assertSessionHasErrors('publication_checklist');
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

it('unpublishes a published item back into drafts', function () {
    $news = News::factory()->published()->create();

    actingAs(newsUser('chief_editor'))
        ->post("/news/{$news->id}/unpublish", ['comment' => 'Материал устарел'])
        ->assertRedirect();

    expect($news->fresh()->status)->toBe(ContentStatus::Draft);
});

it('republishes an unpublished item under the same address', function () {
    $news = News::factory()->published()->create(['slug' => 'same-address']);
    $editor = newsUser('chief_editor');

    actingAs($editor)
        ->post("/news/{$news->id}/unpublish", ['comment' => 'Уточняем данные'])
        ->assertRedirect();

    actingAs($editor)->post("/news/{$news->id}/publish")->assertRedirect();

    expect($news->fresh())
        ->status->toBe(ContentStatus::Published)
        ->slug->toBe('same-address');
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

it('keeps table fill, alignment and column width when sanitising', function () {
    actingAs(newsUser('editor'))->post('/news', [
        'title' => ['ru' => 'Таблица в тексте', 'tg' => '', 'en' => ''],
        'body' => [
            'ru' => '<table><colgroup><col style="width:120px"><col style="min-width:25px"></colgroup>'
                .'<tbody><tr>'
                .'<th colspan="1" style="background-color:#d7e2ea;text-align:center">Заголовок</th>'
                .'<td style="background-color:#f7efd4" colwidth="180">Ячейка</td>'
                .'</tr></tbody></table>'
                .'<p style="background-color:expression(alert(1))">опасно</p>',
            'tg' => '',
            'en' => '',
        ],
        'action' => 'draft',
    ])->assertRedirect('/news');

    $body = News::query()->first()->getTranslation('body', 'ru');

    expect($body)
        ->toContain('<table')
        ->toContain('Заголовок')
        ->toContain('Ячейка')
        ->toContain('background-color:#d7e2ea')
        ->toContain('text-align:center')
        ->toContain('width:120px')
        ->not->toContain('expression');
});

it('preserves image srcset and sizes for responsive images', function () {
    actingAs(newsUser('editor'))->post('/news', [
        'title' => ['ru' => 'Адаптивная картинка', 'tg' => '', 'en' => ''],
        'body' => [
            'ru' => '<img src="/storage/1/x.jpg" '
                .'srcset="/storage/1/conversions/x-sm.jpg 480w, /storage/1/conversions/x-md.jpg 960w" '
                .'sizes="(max-width: 920px) 50vw, 360px" data-media-id="42" '
                .'class="re-img size-medium" alt="ф">',
            'tg' => '',
            'en' => '',
        ],
        'action' => 'draft',
    ])->assertRedirect('/news');

    $body = News::query()->first()->getTranslation('body', 'ru');

    expect($body)
        ->toContain('srcset="/storage/1/conversions/x-sm.jpg 480w')
        ->toContain('sizes=')
        ->toContain('data-media-id="42"')
        ->toContain('size-medium');
});

it('never publishes through quick edit, even when asked to', function () {
    $news = News::factory()->create(['status' => ContentStatus::Draft]);

    actingAs(newsUser('translator'))
        ->patchJson("/news/{$news->id}/quick-update", [
            'title' => 'Перевод заголовка',
            'status' => 'published',
        ])
        ->assertOk();

    expect($news->fresh()->status)->toBe(ContentStatus::Draft);
});

it('lets only publishers move the publication date in quick edit', function () {
    $news = News::factory()->published()->create();

    actingAs(newsUser('translator'))
        ->patchJson("/news/{$news->id}/quick-update", [
            'title' => 'Заголовок',
            'published_at' => now()->addWeek()->format('Y-m-d\TH:i'),
        ])
        ->assertForbidden();

    actingAs(newsUser('editor'))
        ->patchJson("/news/{$news->id}/quick-update", [
            'title' => 'Заголовок',
            'published_at' => '2026-09-01T09:00',
        ])
        ->assertOk();

    expect($news->fresh()->published_at->format('Y-m-d H:i'))->toBe('2026-09-01 09:00');
});

it('accepts an unchanged publication date from users who cannot publish', function () {
    $news = News::factory()->published()->create(['published_at' => '2026-09-10 08:30:00']);

    actingAs(newsUser('translator'))
        ->patchJson("/news/{$news->id}/quick-update", [
            'title' => 'Уточнённый заголовок',
            'published_at' => '2026-09-10T08:30',
        ])
        ->assertOk()
        ->assertJsonPath('pending', true);

    // The news is on the site, so the new title waits for approval.
    expect(PendingChange::query()->sole()->changes)->toHaveKey('title')
        ->and($news->fresh()->published_at->format('Y-m-d H:i'))->toBe('2026-09-10 08:30');
});

it('keeps a quick-edited title in the language it is written in', function () {
    $news = News::factory()->create([
        'title' => ['tg' => 'Сарлавҳаи тоҷикӣ'],
        'status' => ContentStatus::Draft,
    ]);

    actingAs(newsUser('editor'))
        ->patchJson("/news/{$news->id}/quick-update", ['title' => 'Сарлавҳаи нав'])
        ->assertOk();

    $news->refresh();

    expect($news->getTranslation('title', 'tg'))->toBe('Сарлавҳаи нав')
        ->and($news->getTranslation('title', 'ru', false))->toBe('');
});

it('rejects a quick edit made on a stale copy', function () {
    $news = News::factory()->create(['status' => ContentStatus::Draft]);
    $staleVersion = $news->updated_at->toIso8601String();
    $this->travel(1)->minutes();
    $news->forceFill(['is_pinned' => true])->save();

    actingAs(newsUser('editor'))
        ->patchJson("/news/{$news->id}/quick-update", [
            'title' => 'Заголовок',
            '_editorial_version' => $staleVersion,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('editorial_conflict');
});

it('sends a schedule request from someone who cannot publish to approval', function () {
    actingAs(newsUser('regional_editor'))->post('/news', [
        'title' => ['ru' => 'Региональная новость', 'tg' => '', 'en' => ''],
        'action' => 'submit',
        'publish_mode' => 'schedule',
        'scheduled_at' => now()->addDay()->toDateTimeString(),
    ])
        ->assertRedirect('/news')
        ->assertSessionHas('success', 'Новость отправлена на согласование.');

    expect(News::query()->sole()->status)->toBe(ContentStatus::Review);
});

it('tells the author the news went to approval when they cannot publish', function () {
    actingAs(newsUser('regional_editor'))->post('/news', [
        'title' => ['ru' => 'Новость без права публикации', 'tg' => '', 'en' => ''],
        'summary' => ['ru' => 'Лид.', 'tg' => '', 'en' => ''],
        'body' => ['ru' => '<p>Текст.</p>', 'tg' => '', 'en' => ''],
        'action' => 'submit',
        'publish_mode' => 'now',
    ])
        ->assertRedirect('/news')
        ->assertSessionHas('success', 'Новость отправлена на согласование.');

    expect(News::query()->sole()->status)->toBe(ContentStatus::Review);
});

it('publishes news without filling the optional search snippet', function () {
    actingAs(newsUser('chief_editor'))->post('/news', [
        'title' => ['ru' => 'Новость без SEO', 'tg' => '', 'en' => ''],
        'summary' => ['ru' => 'Краткое описание.', 'tg' => '', 'en' => ''],
        'body' => ['ru' => '<p>Текст публикации.</p>', 'tg' => '', 'en' => ''],
        'action' => 'submit',
        'publish_mode' => 'now',
    ])
        ->assertRedirect('/news')
        ->assertSessionHasNoErrors()
        ->assertSessionHas('success', 'Новость опубликована.');

    expect(News::query()->sole()->status)->toBe(ContentStatus::Published);
});

it('offers only the statuses a news item goes through', function () {
    actingAs(newsUser('editor'))->get('/news')
        ->assertInertia(fn (Assert $page) => $page->where(
            'options.statuses',
            fn ($statuses): bool => collect($statuses)->pluck('value')->all() === ['draft', 'review', 'returned', 'scheduled', 'published', 'archived']
                && collect($statuses)->firstWhere('value', 'review')['label'] === 'На согласовании',
        ));
});

it('lists every approval step under «На согласовании»', function () {
    News::factory()->create(['status' => ContentStatus::Review]);
    News::factory()->create(['status' => ContentStatus::TranslationCheck]);
    News::factory()->create(['status' => ContentStatus::Approved]);
    News::factory()->create(['status' => ContentStatus::Draft]);

    actingAs(newsUser('editor'))->get('/news?status=review')
        ->assertInertia(fn (Assert $page) => $page->has('news', 3));
});

it('counts the trash beside the list the way the trash page does', function () {
    $regional = newsUser('regional_editor');
    News::factory()->create(['author_id' => $regional->id])->delete();
    News::factory()->create()->delete();

    actingAs(newsUser('editor'))->get('/news')
        ->assertInertia(fn (Assert $page) => $page->where('trash_count', 2));

    // A regional editor finds only their own materials in the trash.
    actingAs($regional)->get('/news')
        ->assertInertia(fn (Assert $page) => $page->where('trash_count', 1));
});
