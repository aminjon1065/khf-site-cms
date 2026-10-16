<?php

use App\Models\Alert;
use App\Models\News;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\seed;

beforeEach(function () {
    seed(RolePermissionSeeder::class);
});

function viewed(string $type, int $id, string $locale, int $views, int $daysAgo = 0): void
{
    DB::table('content_views')->insert([
        'content_type' => $type,
        'content_id' => $id,
        'date' => today()->subDays($daysAgo)->toDateString(),
        'locale' => $locale,
        'views' => $views,
    ]);
}

it('shows how often the site was read, by day, language, section and material', function () {
    $news = News::factory()->published()->create(['title' => ['ru' => 'Паводок в Хатлоне', 'tg' => 'Обхезӣ', 'en' => '']]);
    $alert = Alert::factory()->published()->create(['title' => ['ru' => 'Сель', 'tg' => 'Сел', 'en' => '']]);
    viewed('news', $news->id, 'tj', 30);
    viewed('news', $news->id, 'ru', 10, daysAgo: 2);
    viewed('alert', $alert->id, 'tj', 60, daysAgo: 1);
    // Outside the 7 days.
    viewed('news', $news->id, 'ru', 999, daysAgo: 20);

    $user = User::factory()->create();
    giveRole($user, 'chief_editor');

    actingAs($user)->get('/statistics')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('statistics/index')
            ->where('report.days', 7)
            ->where('report.total', 100)
            ->has('report.daily', 7)
            ->where('report.daily.6.views', 30)
            ->where('report.by_locale', ['tj' => 90, 'ru' => 10, 'en' => 0])
            ->where('report.top.0.title', 'Сель')
            ->where('report.top.0.views', 60)
            ->where('report.top.0.edit_url', "/alerts/{$alert->id}/edit")
            ->where('report.top.1.title', 'Паводок в Хатлоне')
            ->where('report.top.1.views', 40));

    actingAs($user)->get('/statistics?days=30')
        ->assertInertia(fn (Assert $page) => $page->where('report.total', 1099)->has('report.daily', 30));
});

it('is closed to an account that sees no materials', function () {
    actingAs(User::factory()->create())->get('/statistics')->assertForbidden();
});
