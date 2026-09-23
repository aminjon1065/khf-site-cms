<?php

use App\Enums\ContentStatus;
use App\Models\Alert;
use App\Models\Announcement;
use App\Models\Instruction;
use App\Models\News;
use App\Models\Page;
use App\Models\Project;
use App\Models\SlugRedirect;
use App\Support\Slug;
use Illuminate\Support\Facades\DB;

function renamedNews(string ...$slugs): News
{
    $news = News::factory()->published()->create(['slug' => array_shift($slugs)]);

    foreach ($slugs as $slug) {
        $news->slug = $slug;
        $news->save();
    }

    return $news;
}

it('answers a former address with a permanent redirect to the current one', function () {
    renamedNews('staryj-adres', 'novyj-adres');

    $this->getJson('/api/v1/news/staryj-adres?locale=ru')
        ->assertStatus(301)
        ->assertHeader('Location', '/api/v1/news/novyj-adres?locale=ru');

    $this->getJson('/api/v1/news/novyj-adres?locale=ru')
        ->assertOk()
        ->assertJsonPath('data.slug', 'novyj-adres');
});

it('redirects a former address of every kind of material', function (string $model, string $endpoint) {
    $material = $model::factory()->published()->create(['slug' => 'byvshij-adres']);
    $material->slug = 'tekushchij-adres';
    $material->save();

    $this->getJson("/api/v1/{$endpoint}/byvshij-adres?locale=ru")
        ->assertStatus(301)
        ->assertHeader('Location', "/api/v1/{$endpoint}/tekushchij-adres?locale=ru");

    $this->getJson("/api/v1/{$endpoint}/tekushchij-adres?locale=ru")->assertOk();
})->with([
    'news' => [News::class, 'news'],
    'pages' => [Page::class, 'pages'],
    'projects' => [Project::class, 'projects'],
    'instructions' => [Instruction::class, 'instructions'],
    'announcements' => [Announcement::class, 'announcements'],
    'alerts' => [Alert::class, 'alerts'],
]);

it('leads every former address straight to the current one', function () {
    renamedNews('pervyj', 'vtoroj', 'tretij');

    foreach (['pervyj', 'vtoroj'] as $former) {
        $this->getJson("/api/v1/news/{$former}?locale=ru")
            ->assertStatus(301)
            ->assertHeader('Location', '/api/v1/news/tretij?locale=ru');
    }
});

it('gives a former address to the material that takes it', function () {
    renamedNews('obshchij-adres', 'drugoj-adres');
    News::factory()->published()->create(['slug' => 'obshchij-adres']);

    $this->getJson('/api/v1/news/obshchij-adres?locale=ru')
        ->assertOk()
        ->assertJsonPath('data.slug', 'obshchij-adres');
});

it('drops the redirect when a material gets its former address back', function () {
    renamedNews('vozvrat', 'vremennyj', 'vozvrat');

    $this->getJson('/api/v1/news/vozvrat?locale=ru')->assertOk();
    $this->getJson('/api/v1/news/vremennyj?locale=ru')
        ->assertStatus(301)
        ->assertHeader('Location', '/api/v1/news/vozvrat?locale=ru');

    expect(SlugRedirect::query()->pluck('old_slug')->all())->toBe(['vremennyj']);
});

it('never leads to what the site does not show', function () {
    $hidden = renamedNews('skrytyj-staryj', 'skrytyj-novyj');
    $hidden->forceFill(['status' => ContentStatus::Draft])->save();

    News::factory()->published()->create([
        'slug' => 'tolko-russkij',
        'title' => ['ru' => 'Только по-русски', 'tg' => '', 'en' => ''],
    ])->forceFill(['slug' => 'tolko-russkij-novyj'])->save();

    $this->getJson('/api/v1/news/skrytyj-staryj?locale=ru')->assertNotFound();
    $this->getJson('/api/v1/news/tolko-russkij?locale=tg')->assertNotFound();
    $this->getJson('/api/v1/news/tolko-russkij?locale=ru')->assertStatus(301);
});

it('forgets the former addresses of a material deleted for good', function () {
    $news = renamedNews('udalennyj-staryj', 'udalennyj-novyj');

    $news->delete();
    expect(SlugRedirect::query()->count())->toBe(1);

    $news->forceDelete();
    expect(SlugRedirect::query()->count())->toBe(0);
});

it('cuts an address made from a long title to what the site can hold', function () {
    $title = ['ru' => str_repeat('Сильное землетрясение в горных районах ', 12), 'tg' => '', 'en' => ''];

    $first = News::factory()->create(['slug' => null, 'title' => $title]);
    $second = News::factory()->create(['slug' => null, 'title' => $title]);

    expect(mb_strlen($first->slug))->toBeLessThanOrEqual(Slug::MAX_LENGTH)
        ->and($first->slug)->toStartWith('silnoe-zemletryasenie')->not->toEndWith('-')
        ->and(mb_strlen($second->slug))->toBeLessThanOrEqual(Slug::MAX_LENGTH)
        ->and($second->slug)->toEndWith('-2');
});

it('shortens overlong addresses and keeps the full ones as redirects', function () {
    $long = implode('-', array_fill(0, 25, 'zemletryasenie'));
    $news = News::factory()->published()->create(['slug' => 'vremennyj']);
    DB::table('news')->where('id', $news->id)->update(['slug' => $long, 'updated_at' => '2026-09-01 10:00:00']);

    (require database_path('migrations/2026_09_23_184214_shorten_overlong_slugs.php'))->up();

    $news->refresh();

    expect(mb_strlen($news->slug))->toBeLessThanOrEqual(Slug::MAX_LENGTH)
        ->and($long)->toStartWith($news->slug.'-')
        ->and($news->updated_at->format('Y-m-d H:i'))->toBe('2026-09-01 10:00');

    $this->getJson("/api/v1/news/{$long}?locale=ru")
        ->assertStatus(301)
        ->assertHeader('Location', "/api/v1/news/{$news->slug}?locale=ru");
});
