<?php

use App\Models\Alert;
use App\Models\Announcement;
use App\Models\Document;
use App\Models\Instruction;
use App\Models\News;
use App\Models\Page;
use App\Models\Project;
use Database\Seeders\HomeBlockSeeder;
use Database\Seeders\MenuSeeder;
use Database\Seeders\RegionSeeder;
use Database\Seeders\SettingSeeder;
use Database\Seeders\TaxonomySeeder;
use Spectator\Spectator;

use function Pest\Laravel\seed;

// D-3: contract tests. Functional tests elsewhere check specific values;
// these only check that real controller responses conform to openapi.yaml,
// so the two are kept in separate files rather than mixed into one.
//
// Every request pins ?locale=ru explicitly. Without it, Symfony's test
// client's default Accept-Language (en-us,en;q=0.5) resolves the API
// locale to `en` — and every factory here follows the project's own
// `en => ''` convention, so PublicLocale::available() (title->en is empty)
// would silently 404 every {slug} route. Same root cause A-1 already hit
// and documented for other Feature tests outside tests/Feature/Api.
beforeEach(function () {
    // Self-contained rather than relying on .env/phpunit.xml: base_path()
    // can only be computed at runtime, and the prefix strip is easy to
    // silently get wrong (spectator:routes showed every /api/v1/* route as
    // "undocumented" until this was set — it does NOT derive the prefix
    // from the spec's own `servers[].url`).
    config([
        'spectator.sources.local.base_path' => base_path(),
        'spectator.path_prefix' => 'api/v1',
    ]);
    Spectator::using('openapi.yaml');

    seed([RegionSeeder::class, HomeBlockSeeder::class, SettingSeeder::class, MenuSeeder::class, TaxonomySeeder::class]);

    $this->alert = Alert::factory()->published()->create();
    $this->announcement = Announcement::factory()->published()->create();
    $this->document = Document::factory()->published()->create();
    $this->instruction = Instruction::factory()->published()->create();
    $this->news = News::factory()->published()->create();
    $this->page = Page::factory()->published()->create();
    $this->project = Project::factory()->published()->create();
});

it('validates GET /health and /ready', function () {
    // Whichever status they actually return (200 or 503, e.g. a fresh test
    // DB has no scheduler check-in yet) — the spec documents both as the
    // same body shape, so this is still a real assertion, not a tautology.
    $this->getJson('/api/v1/health')->assertValidRequest()->assertValidResponse();
    $this->getJson('/api/v1/ready')->assertValidRequest()->assertValidResponse();
});

it('validates GET /home', function () {
    $this->getJson('/api/v1/home?locale=ru')->assertValidRequest()->assertValidResponse(200);
});

it('validates GET /settings', function () {
    $this->getJson('/api/v1/settings?locale=ru')->assertValidRequest()->assertValidResponse(200);
});

it('validates GET /menu', function () {
    $this->getJson('/api/v1/menu?locale=ru')->assertValidRequest()->assertValidResponse(200);
});

it('validates GET /search, including its 422', function () {
    $title = $this->news->getTranslation('title', 'ru', false);
    $this->getJson('/api/v1/search?locale=ru&q='.urlencode($title))
        ->assertValidRequest()->assertValidResponse(200);

    $this->getJson('/api/v1/search?locale=ru')->assertValidResponse(422); // missing required q
});

it('validates POST /submissions, including its 422', function () {
    $this->postJson('/api/v1/submissions?locale=ru', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'message' => 'This is a test message body, long enough to pass validation.',
        'consent' => true,
    ])->assertValidRequest()->assertValidResponse(201);

    $this->postJson('/api/v1/submissions?locale=ru', [])->assertValidResponse(422);
});

it('validates GET /news and /news/{slug}, including its 404', function () {
    $this->getJson('/api/v1/news?locale=ru')->assertValidRequest()->assertValidResponse(200);
    $this->getJson("/api/v1/news/{$this->news->slug}?locale=ru")->assertValidRequest()->assertValidResponse(200);
    $this->getJson('/api/v1/news/no-such-slug?locale=ru')->assertValidResponse(404);
});

it('validates GET /pages and /pages/{slug}', function () {
    $this->getJson('/api/v1/pages?locale=ru')->assertValidRequest()->assertValidResponse(200);
    $this->getJson("/api/v1/pages/{$this->page->slug}?locale=ru")->assertValidRequest()->assertValidResponse(200);
});

it('validates GET /categories', function () {
    $this->getJson('/api/v1/categories?locale=ru')->assertValidRequest()->assertValidResponse(200);
    $this->getJson('/api/v1/categories?locale=ru&type=news')->assertValidRequest()->assertValidResponse(200);
});

it('validates GET /instructions and /instructions/{slug}', function () {
    $this->getJson('/api/v1/instructions?locale=ru')->assertValidRequest()->assertValidResponse(200);
    $this->getJson("/api/v1/instructions/{$this->instruction->slug}?locale=ru")->assertValidRequest()->assertValidResponse(200);
});

it('validates GET /documents', function () {
    $this->getJson('/api/v1/documents?locale=ru')->assertValidRequest()->assertValidResponse(200);
});

it('validates GET /projects and /projects/{slug}', function () {
    $this->getJson('/api/v1/projects?locale=ru')->assertValidRequest()->assertValidResponse(200);
    $this->getJson("/api/v1/projects/{$this->project->slug}?locale=ru")->assertValidRequest()->assertValidResponse(200);
});

it('validates GET /announcements and /announcements/{slug}', function () {
    $this->getJson('/api/v1/announcements?locale=ru')->assertValidRequest()->assertValidResponse(200);
    $this->getJson("/api/v1/announcements/{$this->announcement->slug}?locale=ru")->assertValidRequest()->assertValidResponse(200);
});

it('validates GET /alerts, /alerts/active and /alerts/{slug}', function () {
    $this->getJson('/api/v1/alerts?locale=ru')->assertValidRequest()->assertValidResponse(200);
    $this->getJson('/api/v1/alerts/active?locale=ru')->assertValidRequest()->assertValidResponse(200);
    $this->getJson("/api/v1/alerts/{$this->alert->slug}?locale=ru")->assertValidRequest()->assertValidResponse(200);
});

it('validates GET /regions and /regions/directory', function () {
    $this->getJson('/api/v1/regions?locale=ru')->assertValidRequest()->assertValidResponse(200);
    $this->getJson('/api/v1/regions/directory?locale=ru')->assertValidRequest()->assertValidResponse(200);
});
