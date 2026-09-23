<?php

use App\Enums\ContentStatus;
use App\Enums\Severity;
use App\Jobs\RevalidateFrontend;
use App\Models\Alert;
use App\Models\Document;
use App\Models\News;
use App\Models\Page;
use App\Models\PendingChange;
use App\Models\Region;
use App\Models\Tag;
use App\Models\User;
use App\Notifications\WorkflowNotification;
use Database\Seeders\RegionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\seed;

beforeEach(function () {
    seed([RolePermissionSeeder::class, RegionSeeder::class]);
    Notification::fake();
    Queue::fake();
});

function changeUser(string $role): User
{
    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

function liveNews(): News
{
    return News::factory()->published()->create([
        'title' => ['ru' => 'Опубликованный заголовок', 'tg' => 'Сарлавҳа', 'en' => ''],
        'summary' => ['ru' => 'Лид', 'tg' => 'Лид', 'en' => ''],
        'body' => ['ru' => '<p>Текст на сайте.</p>', 'tg' => '<p>Матн.</p>', 'en' => ''],
    ]);
}

/**
 * @return array<string, mixed>
 */
function newsForm(News $news, array $overrides = []): array
{
    return array_replace_recursive([
        '_method' => 'put',
        'title' => $news->getTranslations('title') + ['ru' => '', 'tg' => '', 'en' => ''],
        'summary' => $news->getTranslations('summary') + ['ru' => '', 'tg' => '', 'en' => ''],
        'body' => $news->getTranslations('body') + ['ru' => '', 'tg' => '', 'en' => ''],
        'seo' => $news->seo,
        'category_id' => $news->category_id,
        'is_pinned' => $news->is_pinned ? '1' : '0',
        'show_on_home' => $news->show_on_home ? '1' : '0',
        'action' => 'draft',
    ], $overrides);
}

it('keeps the live version when someone who cannot publish edits a published news item', function () {
    $news = liveNews();
    $translator = changeUser('translator');
    $approver = changeUser('approver');

    actingAs($translator)
        ->post("/news/{$news->id}", newsForm($news, ['title' => ['ru' => 'Исправленный заголовок']]))
        ->assertRedirect("/news/{$news->id}/edit")
        ->assertSessionHas('success', 'Изменения отправлены на согласование. На сайте пока прежняя версия.');

    $change = PendingChange::query()->sole();

    expect($news->fresh()->getTranslation('title', 'ru'))->toBe('Опубликованный заголовок')
        ->and($news->fresh()->status)->toBe(ContentStatus::Published)
        ->and($change->isPending())->toBeTrue()
        ->and($change->user_id)->toBe($translator->id)
        ->and(array_keys($change->changes))->toBe(['title']);

    Notification::assertSentTo(
        $approver,
        WorkflowNotification::class,
        fn (WorkflowNotification $n): bool => $n->toArray($approver)['url'] === "/approvals?change={$change->id}",
    );
});

it('does not take photos and files into a proposal', function () {
    $news = liveNews();

    actingAs(changeUser('translator'))
        ->post("/news/{$news->id}", newsForm($news, [
            'title' => ['ru' => 'Новый заголовок'],
            'cover' => UploadedFile::fake()->image('cover.jpg'),
        ]))
        ->assertSessionHasErrors('media');

    expect(PendingChange::query()->count())->toBe(0);
});

it('refuses an empty proposal', function () {
    $news = liveNews();

    actingAs(changeUser('translator'))
        ->post("/news/{$news->id}", newsForm($news))
        ->assertSessionHasErrors('changes');
});

it('publishes the proposal when an approver applies it', function () {
    $news = liveNews();
    $tag = Tag::query()->create(['name' => ['ru' => 'учения'], 'slug' => 'ucheniya']);
    $translator = changeUser('translator');

    actingAs($translator)->post("/news/{$news->id}", newsForm($news, [
        'title' => ['ru' => 'Исправленный заголовок'],
        'tags' => [$tag->id],
    ]));
    $change = PendingChange::query()->sole();

    actingAs(changeUser('approver'))
        ->post("/approvals/changes/{$change->id}/apply")
        ->assertRedirect('/approvals');

    $news->refresh();

    expect($news->getTranslation('title', 'ru'))->toBe('Исправленный заголовок')
        ->and($news->tags()->pluck('tags.id')->all())->toBe([$tag->id])
        ->and($change->fresh()->status)->toBe(PendingChange::APPLIED);

    Notification::assertSentTo($translator, WorkflowNotification::class, fn (WorkflowNotification $n): bool => $n->title === 'Ваши изменения опубликованы');
    Queue::assertPushed(RevalidateFrontend::class, fn (RevalidateFrontend $job): bool => $job->type === 'news' && $job->id === $news->id);
});

it('sends the reviewer comment back when a proposal is rejected', function () {
    $news = liveNews();
    $translator = changeUser('translator');

    actingAs($translator)->post("/news/{$news->id}", newsForm($news, ['title' => ['ru' => 'Спорный заголовок']]));
    $change = PendingChange::query()->sole();

    actingAs(changeUser('approver'))
        ->post("/approvals/changes/{$change->id}/reject", ['comment' => 'Заголовок не соответствует тексту.'])
        ->assertRedirect('/approvals');

    expect($news->fresh()->getTranslation('title', 'ru'))->toBe('Опубликованный заголовок')
        ->and($change->fresh()->status)->toBe(PendingChange::REJECTED);

    Notification::assertSentTo($translator, WorkflowNotification::class, fn (WorkflowNotification $n): bool => str_contains($n->message, 'Заголовок не соответствует тексту.'));
});

it('does not let someone without the approve permission apply a proposal', function () {
    $news = liveNews();
    actingAs(changeUser('translator'))->post("/news/{$news->id}", newsForm($news, ['title' => ['ru' => 'Правка']]));
    $change = PendingChange::query()->sole();

    actingAs(changeUser('editor'))
        ->post("/approvals/changes/{$change->id}/apply")
        ->assertForbidden();
});

it('lets an editor who may publish update a published item directly and refreshes the site', function () {
    $news = liveNews();

    actingAs(changeUser('editor'))
        ->post("/news/{$news->id}", newsForm($news, ['title' => ['ru' => 'Обновлённый заголовок']]))
        ->assertRedirect();

    expect($news->fresh()->getTranslation('title', 'ru'))->toBe('Обновлённый заголовок')
        ->and(PendingChange::query()->count())->toBe(0);

    Queue::assertPushed(RevalidateFrontend::class, fn (RevalidateFrontend $job): bool => $job->type === 'news' && $job->event === 'updated');
});

it('reopens the editor on the proposal for its author and lists it for approvers', function () {
    $news = liveNews();
    $translator = changeUser('translator');
    actingAs($translator)->post("/news/{$news->id}", newsForm($news, ['title' => ['ru' => 'Предложенный заголовок']]));
    $change = PendingChange::query()->sole();

    actingAs($translator)->get("/news/{$news->id}/edit")
        ->assertInertia(fn (Assert $page) => $page
            ->where('news.title.ru', 'Предложенный заголовок')
            ->where('pending_change.is_mine', true)
            ->where('changes_need_approval', true));

    actingAs(changeUser('approver'))->get("/approvals?change={$change->id}")
        ->assertInertia(fn (Assert $page) => $page
            ->where('detail.change_id', $change->id)
            ->where('detail.diff', fn ($diff): bool => collect($diff)->contains(
                fn (array $row): bool => $row['label'] === 'Заголовок (РУ)'
                    && $row['before'] === 'Опубликованный заголовок'
                    && $row['after'] === 'Предложенный заголовок',
            )));
});

it('holds changes to a published page from someone who cannot publish pages', function () {
    $page = Page::factory()->published()->create([
        'slug' => 'history',
        'title' => ['ru' => 'История'],
        'body' => ['ru' => '<p>Текст.</p>'],
    ]);

    actingAs(changeUser('translator'))->post("/pages/{$page->id}", [
        '_method' => 'put',
        'title' => ['ru' => 'История', 'tg' => 'Таърих', 'en' => ''],
        'body' => ['ru' => '<p>Текст.</p>', 'tg' => '<p>Матн.</p>', 'en' => ''],
        'action' => 'draft',
    ])->assertRedirect("/pages/{$page->id}/edit");

    expect($page->fresh()->getTranslation('title', 'tg', false))->toBe('')
        ->and(PendingChange::query()->sole()->changeable_id)->toBe($page->id);
});

it('keeps document files out of a proposal', function () {
    Storage::fake('content_private');
    $document = Document::factory()->create([
        'status' => ContentStatus::Published,
        'published_at' => now(),
    ]);

    actingAs(changeUser('editor'))->post("/documents/{$document->id}", [
        '_method' => 'put',
        'name' => ['ru' => 'Новое название', 'tg' => '', 'en' => ''],
        'doc_type' => $document->doc_type->value,
        'file_ru' => UploadedFile::fake()->create('law.pdf', 100, 'application/pdf'),
        'action' => 'draft',
    ])->assertSessionHasErrors('media');
});

it('proposes alert changes with their regions and applies them together', function () {
    $alert = Alert::factory()->create([
        'status' => ContentStatus::Published,
        'published_at' => now(),
        'severity' => Severity::Warning,
    ]);
    $region = Region::query()->where('code', 'khatlon')->sole();
    $editor = changeUser('editor');

    actingAs($editor)->post("/alerts/{$alert->id}", [
        '_method' => 'put',
        'internal_title' => $alert->internal_title,
        'hazard_type' => $alert->hazard_type->value,
        'severity' => 'warning',
        'territory_type' => 'regions',
        'regions' => [$region->id],
        'title' => ['ru' => 'Уточнённое предупреждение'],
        'action' => 'draft',
    ])->assertRedirect("/alerts/{$alert->id}/edit");

    $change = PendingChange::query()->sole();

    expect($change->relations)->toHaveKey('regions')
        ->and($alert->fresh()->getTranslation('title', 'ru', false))->not->toBe('Уточнённое предупреждение');

    actingAs(changeUser('alert_operator'))->post("/approvals/changes/{$change->id}/apply")->assertRedirect('/approvals');

    expect($alert->fresh()->getTranslation('title', 'ru'))->toBe('Уточнённое предупреждение')
        ->and($alert->fresh()->regions()->pluck('regions.id')->all())->toBe([$region->id]);
});

it('shows approvers the whole material, not only its lead', function () {
    $news = News::factory()->create(['status' => ContentStatus::Review]);

    actingAs(changeUser('approver'))->get("/approvals?type=news&id={$news->id}")
        ->assertInertia(fn (Assert $page) => $page
            ->where('detail.change_id', null)
            ->where('detail.preview_url', fn ($url): bool => is_string($url) && str_contains($url, "/editorial/news/{$news->id}/preview")));
});
