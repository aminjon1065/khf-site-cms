<?php

use App\Enums\ContentStatus;
use App\Models\Activity;
use App\Models\Alert;
use App\Models\Announcement;
use App\Models\Document;
use App\Models\Instruction;
use App\Models\News;
use App\Models\Page;
use App\Models\Project;
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

function dashboardUser(string $role): User
{
    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

it('hides recent audit activity from users without the users view permission', function () {
    Activity::query()->create([
        'log_name' => 'settings',
        'description' => 'Изменена настройка',
        'event' => 'updated',
    ]);

    actingAs(dashboardUser('editor'))->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboard')
            ->has('activity', 0));
});

it('shows recent audit activity to administrators', function () {
    Activity::query()->create([
        'log_name' => 'settings',
        'description' => 'Изменена настройка',
        'event' => 'updated',
    ]);

    actingAs(dashboardUser('admin'))->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboard')
            ->has('activity.0'));
});

it('limits dashboard data to the assigned region and own editorial content', function () {
    $assignedRegion = Region::query()->where('code', 'khatlon')->firstOrFail();
    $foreignRegion = Region::query()->where('code', 'sughd')->firstOrFail();
    $user = dashboardUser('regional_editor');
    $user->update(['region_id' => $assignedRegion->id]);

    $ownAlert = Alert::factory()->published()->create([
        'starts_at' => now()->subHour(),
        'ends_at' => now()->addHour(),
    ]);
    $ownAlert->regions()->attach($assignedRegion);

    $foreignAlert = Alert::factory()->published()->create([
        'starts_at' => now()->subHour(),
        'ends_at' => now()->addHour(),
    ]);
    $foreignAlert->regions()->attach($foreignRegion);

    News::factory()->create(['author_id' => $user->id, 'status' => 'draft']);
    News::factory()->create(['author_id' => User::factory()->create()->id, 'status' => 'draft']);

    actingAs($user)->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('metrics.0.value', 1)
            ->where('metrics.1.value', 1)
            ->has('activeAlerts', 1)
            ->has('regionStatuses', 1));
});

// D-6 (CMS_AUDIT.md P2): metrics/tasks/calendar used to only ever look at
// Alert and News, silently excluding Instruction/Document/Project/
// Announcement/Page from every widget except the type-agnostic activity
// feed — see DashboardController for the per-widget expansion.
it('counts drafts and review across every workflow type, not just alerts and news', function () {
    Instruction::factory()->create(['status' => ContentStatus::Draft]);
    Document::factory()->create(['status' => ContentStatus::Draft]);
    Page::factory()->create(['status' => ContentStatus::Draft]);
    Project::factory()->create(['status' => ContentStatus::Review]);
    Announcement::factory()->create(['status' => ContentStatus::TranslationCheck]);

    actingAs(dashboardUser('admin'))->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('metrics.1.value', 3)
            ->where('metrics.2.value', 2));
});

it('includes non-alert workflow types pending approval in the attention queue', function () {
    Project::factory()->create(['status' => ContentStatus::Review]);

    actingAs(dashboardUser('chief_editor'))->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('tasks', 1)
            ->where('tasks.0.href', '/approvals')
            ->where('taskCenter.href', '/approvals'));
});

it('gives translators incomplete translation tasks and their queue link', function () {
    $news = News::factory()->create([
        'status' => ContentStatus::TranslationCheck,
    ]);

    actingAs(dashboardUser('translator'))->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('tasks', 1)
            ->where('tasks.0.kind', 'translation')
            ->where('tasks.0.action', 'Перевести')
            ->where('tasks.0.href', "/news/{$news->id}/edit")
            ->where('taskCenter.href', '/editorial/translations')
            ->where('taskCenter.label', 'Очередь переводов'));
});

it('prioritizes the authors returned material and only shows their own drafts', function () {
    $editor = dashboardUser('editor');
    $otherEditor = dashboardUser('editor');
    $returned = Page::factory()->create([
        'author_id' => $editor->id,
        'status' => ContentStatus::Returned,
    ]);
    $draft = News::factory()->create([
        'author_id' => $editor->id,
        'status' => ContentStatus::Draft,
    ]);
    News::factory()->create([
        'author_id' => $otherEditor->id,
        'status' => ContentStatus::Draft,
    ]);

    actingAs($editor)->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('tasks', 2)
            ->where('tasks.0.kind', 'returned')
            ->where('tasks.0.href', "/pages/{$returned->id}/edit")
            ->where('tasks.1.kind', 'draft')
            ->where('tasks.1.href', "/news/{$draft->id}/edit"));
});

it('does not offer viewers edit actions for expiring alerts', function () {
    Alert::factory()->published()->create([
        'starts_at' => now()->subHour(),
        'ends_at' => now()->addHour(),
    ]);

    actingAs(dashboardUser('viewer'))->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('tasks', 0)
            ->where('taskCenter', null));
});

it('shows recently published non-alert, non-news content on the calendar', function () {
    Project::factory()->create(['status' => ContentStatus::Published, 'published_at' => now()]);

    actingAs(dashboardUser('admin'))->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('calendar', 1));
});

it('links empty dashboard metrics to the default filtered lists', function () {
    actingAs(dashboardUser('admin'))->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('metrics.0.key', 'active')
            ->where('metrics.0.href', route('alerts.index', ['view' => 'active'], false))
            ->where('metrics.1.key', 'drafts')
            ->where('metrics.1.href', route('news.index', ['view' => 'drafts'], false))
            ->where('metrics.2.key', 'review')
            ->where('metrics.2.href', route('approvals', [], false))
            ->where('metrics.3.key', 'scheduled')
            ->where('metrics.3.href', route('news.index', ['view' => 'scheduled'], false))
            ->where('metrics.4.key', 'published_month')
            ->where('metrics.4.href', route('news.index', ['view' => 'published'], false))
            ->where('metrics.5.key', 'translations')
            ->where('metrics.5.href', route('editorial.translations', [], false)));
});

it('sends editors to a review list instead of the approval center', function () {
    actingAs(dashboardUser('editor'))->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('metrics.2.href', route('news.index', ['view' => 'review'], false)));
});

it('does not send viewers to the translation queue they cannot open', function () {
    actingAs(dashboardUser('viewer'))->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('metrics.2.href', route('news.index', ['view' => 'review'], false))
            ->where('metrics.5.href', null));
});

it('points the drafts card at the type that actually has drafts', function () {
    Page::factory()->create(['status' => ContentStatus::Draft]);

    actingAs(dashboardUser('admin'))->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('metrics.1.href', route('pages.index', ['view' => 'drafts'], false)));
});

it('points the scheduled card at alerts when only alerts are scheduled', function () {
    Alert::factory()->create([
        'status' => ContentStatus::Scheduled,
        'scheduled_at' => now()->addDay(),
    ]);

    actingAs(dashboardUser('editor'))->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('metrics.3.href', route('alerts.index', ['view' => 'scheduled'], false)));
});

it('lets an editor open every default metric destination', function (string $path) {
    actingAs(dashboardUser('editor'))->get($path)->assertOk();
})->with([
    '/alerts?view=active',
    '/news?view=drafts',
    '/news?view=review',
    '/news?view=scheduled',
    '/news?view=published',
    '/editorial/translations',
]);
