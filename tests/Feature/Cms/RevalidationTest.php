<?php

use App\Enums\ContentStatus;
use App\Models\News;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\seed;

beforeEach(function () {
    seed(RolePermissionSeeder::class);
});

function revalidationEditor(): User
{
    $user = User::factory()->create();
    $user->assignRole('chief_editor');

    return $user;
}

it('sends exactly one authenticated POST to the frontend when publishing and a webhook is configured', function () {
    config([
        'services.frontend.revalidation_url' => 'http://localhost:3000/api/revalidate',
        'services.frontend.revalidation_secret' => 'test-secret',
    ]);

    $news = News::factory()->create();

    actingAs(revalidationEditor())
        ->post("/news/{$news->id}/publish")
        ->assertRedirect();

    expect($news->fresh()->status)->toBe(ContentStatus::Published);

    Http::assertSentCount(1);
    Http::assertSent(function (Request $request): bool {
        return $request->url() === 'http://localhost:3000/api/revalidate'
            && $request->hasHeader('Authorization', 'Bearer test-secret')
            && $request->data() === ['tag' => 'cms'];
    });
});

it('sends nothing when the frontend webhook is not configured', function () {
    config([
        'services.frontend.revalidation_url' => '',
        'services.frontend.revalidation_secret' => '',
    ]);

    $news = News::factory()->create();

    actingAs(revalidationEditor())
        ->post("/news/{$news->id}/publish")
        ->assertRedirect();

    expect($news->fresh()->status)->toBe(ContentStatus::Published);

    Http::assertNothingSent();
});

it('does not turn a frontend outage into a 500 under the sync queue connection', function () {
    config([
        'services.frontend.revalidation_url' => 'http://localhost:3000/api/revalidate',
        'services.frontend.revalidation_secret' => 'test-secret',
    ]);

    Http::fake(function (): void {
        throw new ConnectionException('Connection refused.');
    });

    $news = News::factory()->create();

    actingAs(revalidationEditor())
        ->post("/news/{$news->id}/publish")
        ->assertRedirect();

    expect($news->fresh()->status)->toBe(ContentStatus::Published);
});

// D-5: phpunit.xml forces QUEUE_CONNECTION=sync for every other test in this
// suite, so the `!== 'sync'` branch in RevalidateFrontend::handle() (the one
// that actually matters once a real deploy runs `database`/`redis`) had
// never been exercised. Overriding queue.default here — instead of faking
// the queue — lets the job really round-trip through the `jobs` table via
// the database driver, same as `queue:work` would in production.
it('re-throws so the worker retries once a real (non-sync) queue connection is active', function () {
    config([
        'services.frontend.revalidation_url' => 'http://localhost:3000/api/revalidate',
        'services.frontend.revalidation_secret' => 'test-secret',
        'queue.default' => 'database',
    ]);

    Http::fake(function (): void {
        throw new ConnectionException('Connection refused.');
    });

    $news = News::factory()->create();

    actingAs(revalidationEditor())
        ->post("/news/{$news->id}/publish")
        ->assertRedirect();

    expect($news->fresh()->status)->toBe(ContentStatus::Published)
        ->and(DB::table('jobs')->count())->toBe(1);

    $this->artisan('queue:work', ['--once' => true])->assertSuccessful();

    // Still on the queue for a retry (not deleted as "successful") proves
    // the exception propagated out of handle() instead of being swallowed.
    expect(DB::table('jobs')->count())->toBe(1)
        ->and(DB::table('jobs')->value('attempts'))->toBe(1);
});
