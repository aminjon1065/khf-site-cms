<?php

use App\Enums\ContentStatus;
use App\Models\News;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
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
