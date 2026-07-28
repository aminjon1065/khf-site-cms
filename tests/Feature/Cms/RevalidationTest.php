<?php

use App\Enums\ContentStatus;
use App\Jobs\RevalidateFrontend;
use App\Models\News;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

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

// Проверяем именно ДИСПАТЧ, а не сам HTTP-запрос: джоба ставится через
// `->afterCommit()`, а под `RefreshDatabase` внешняя транзакция никогда не
// коммитится, поэтому внутри запроса она принципиально не выполнится.
// Поведение самого запроса (авторизация, 401/500, обрыв связи, поведение
// под sync и под реальной очередью) покрыто в
// `tests/Feature/Jobs/RevalidateFrontendTest.php`.
it('dispatches exactly one granular revalidation job when publishing and a webhook is configured', function () {
    config([
        'services.frontend.revalidation_url' => 'http://localhost:3000/api/revalidate',
        'services.frontend.revalidation_secret' => 'test-secret',
    ]);

    Queue::fake();

    $news = News::factory()->create();

    actingAs(revalidationEditor())
        ->post("/news/{$news->id}/publish")
        ->assertRedirect();

    expect($news->fresh()->status)->toBe(ContentStatus::Published);

    Queue::assertPushed(RevalidateFrontend::class, 1);
    Queue::assertPushed(function (RevalidateFrontend $job) use ($news): bool {
        $payload = $job->payload();

        // Полезная нагрузка стала гранулярной (O-009): вместо единственного
        // `{"tag":"cms"}` уходит тип, идентификатор, slug, локали, событие и
        // готовый список тегов — фронт инвалидирует только затронутое.
        return $payload['type'] === 'news'
            && $payload['id'] === $news->id
            && $payload['event'] === 'published'
            && in_array('cms:news:ru', $payload['tags'], true)
            && $job->afterCommit === true;
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

// D-5: джоба должна уходить на выделенную очередь ревалидации, а не в
// общий поток — иначе всплеск публикаций задержит уведомления и наоборот.
// Сам рестарт/ретрай при недоступном фронте проверяется на уровне джобы
// (`RevalidateFrontendTest`: rethrow при non-sync соединении).
it('queues revalidation on its own queue so it cannot block other work', function () {
    config([
        'services.frontend.revalidation_url' => 'http://localhost:3000/api/revalidate',
        'services.frontend.revalidation_secret' => 'test-secret',
    ]);

    Queue::fake();

    $news = News::factory()->create();

    actingAs(revalidationEditor())
        ->post("/news/{$news->id}/publish")
        ->assertRedirect();

    Queue::assertPushed(
        RevalidateFrontend::class,
        fn (RevalidateFrontend $job): bool => $job->queue === (string) config('queue.names.revalidation'),
    );
});
