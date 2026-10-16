<?php

use App\Enums\SubmissionStatus;
use App\Models\Region;
use App\Models\Submission;
use App\Models\User;
use App\Notifications\SubmissionStatusChanged;
use App\Support\PublicSite;
use Database\Seeders\RegionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\seed;

beforeEach(function () {
    seed(RolePermissionSeeder::class);
});

function subUser(string $role): User
{
    $user = User::factory()->create();
    giveRole($user, $role);

    return $user;
}

it('lets a chief editor open the submissions list', function () {
    actingAs(subUser('chief_editor'))->get('/submissions')->assertOk();
});

it('clamps an excessive submissions page size', function () {
    actingAs(subUser('chief_editor'))->get('/submissions?per_page=999999')
        ->assertInertia(fn ($page) => $page
            ->component('submissions/index')
            ->where('meta.per_page', 100));
});

it('forbids a role without submissions access from the list', function () {
    actingAs(subUser('editor'))->get('/submissions')->assertForbidden();
});

it('shows a submission detail to an authorized user', function () {
    $submission = Submission::factory()->create();

    actingAs(subUser('chief_editor'))->get("/submissions/{$submission->id}")->assertOk();
});

it('updates the status and assignee', function () {
    $submission = Submission::factory()->create();
    $assignee = subUser('admin');

    actingAs(subUser('chief_editor'))->put("/submissions/{$submission->id}", [
        'status' => 'in_progress',
        'assigned_to' => $assignee->id,
    ])->assertRedirect();

    $submission->refresh();

    expect($submission->status)->toBe(SubmissionStatus::InProgress)
        ->and($submission->assigned_to)->toBe($assignee->id);
});

it('adds an internal comment attributed to the current user', function () {
    $submission = Submission::factory()->create();
    $user = subUser('chief_editor');

    actingAs($user)->post("/submissions/{$submission->id}/comments", [
        'body' => 'Передано в региональное управление.',
    ])->assertRedirect();

    expect($submission->comments()->count())->toBe(1)
        ->and($submission->comments()->first()->user_id)->toBe($user->id);
});

it('forbids a viewer from updating a submission', function () {
    $submission = Submission::factory()->create();

    actingAs(subUser('viewer'))->put("/submissions/{$submission->id}", [
        'status' => 'reviewed',
    ])->assertForbidden();
});

it('soft-deletes a submission for an authorized user', function () {
    $submission = Submission::factory()->create();

    actingAs(subUser('chief_editor'))->delete("/submissions/{$submission->id}")->assertRedirect();

    expect(Submission::query()->find($submission->id))->toBeNull()
        ->and(Submission::withTrashed()->find($submission->id))->not->toBeNull();
});

it('offers an appeal to everyone who may work on appeals, whatever their role is called', function () {
    $submission = Submission::factory()->create();
    $chief = subUser('chief_editor');
    $clerk = subUser(customRole('appeals_clerk', ['submissions.view', 'submissions.edit']));
    subUser('editor');

    actingAs($chief)->get("/submissions/{$submission->id}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where(
            'reference.assignees',
            fn ($assignees): bool => collect($assignees)->pluck('value')->sort()->values()->all()
                === collect([$chief->id, $clerk->id])->sort()->values()->all(),
        ));
});

it('filters appeals by the region the citizen picked', function () {
    seed(RegionSeeder::class);
    $khatlon = Region::query()->where('code', 'khatlon')->firstOrFail();
    $sughd = Region::query()->where('code', 'sughd')->firstOrFail();

    Submission::factory()->create(['region_id' => $khatlon->id, 'phone' => '+992 93 000 00 01', 'email' => null]);
    Submission::factory()->create(['region_id' => $sughd->id]);
    Submission::factory()->create(['region_id' => null]);

    actingAs(subUser('chief_editor'))->get('/submissions?region=khatlon')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('submissions', 1)
            ->where('submissions.0.region', 'Хатлонская область')
            ->where('submissions.0.phone', '+992 93 000 00 01')
            ->where('submissions.0.email', null)
            ->where('filters.region', 'khatlon')
            ->has('options.regions', 5));
});

it('tells staff what the citizen sees for each status', function () {
    $submission = Submission::factory()->create();

    actingAs(subUser('chief_editor'))->get("/submissions/{$submission->id}")
        ->assertInertia(fn (Assert $page) => $page
            ->where('reference.public_stages.new', 'Получено')
            ->where('reference.public_stages.awaiting', 'На рассмотрении')
            ->where('reference.public_stages.spam', 'Оставлено без рассмотрения'));
});

it('emails the citizen when the appeal moves to another stage', function () {
    Notification::fake();
    $submission = Submission::factory()->create(['email' => 'citizen@example.tj', 'locale' => 'tg']);

    actingAs(subUser('chief_editor'))
        ->put("/submissions/{$submission->id}", ['status' => 'in_progress', 'assigned_to' => null])
        ->assertSessionHas('success', 'Обращение обновлено. Заявителю отправлено письмо о новом статусе.');

    Notification::assertSentOnDemand(
        SubmissionStatusChanged::class,
        fn (SubmissionStatusChanged $notification, array $channels, object $notifiable): bool => $notifiable->routes['mail'] === 'citizen@example.tj'
            && $notification->locale === 'tg',
    );
});

it('writes the email in the appeal\'s language, with only the stage and where to check it', function () {
    $submission = Submission::factory()->create([
        'name' => 'Фарид Каримов',
        'locale' => 'tg',
        'status' => SubmissionStatus::Completed,
        'message' => 'Матни махфии муроҷиат',
    ]);

    $mail = (new SubmissionStatusChanged($submission))->toMail(new stdClass);
    $html = (string) $mail->render();

    expect($mail->subject)->toBe("Муроҷиати {$submission->tracking_number}: Баррасӣ шуд")
        ->and($html)->toContain('Ассалому алайкум, Фарид Каримов!')
        ->toContain('Баррасӣ анҷом ёфт.')
        ->toContain(PublicSite::url('/contacts', 'tg').'#status')
        ->toContain('/tj/contacts')
        ->toContain('Кумитаи ҳолатҳои фавқулода')
        ->not->toContain('Матни махфии муроҷиат')
        ->not->toContain((string) config('app.url').'/login');
});

it('does not email within the same stage, for spam or without an email', function () {
    Notification::fake();
    $editor = subUser('chief_editor');

    // «Просмотрено» → «В работе»: for the citizen both are «На рассмотрении».
    $reviewed = Submission::factory()->create(['status' => SubmissionStatus::Reviewed]);
    actingAs($editor)->put("/submissions/{$reviewed->id}", ['status' => 'in_progress', 'assigned_to' => null]);

    $spam = Submission::factory()->create();
    actingAs($editor)->put("/submissions/{$spam->id}", ['status' => 'spam', 'assigned_to' => null]);

    $phoneOnly = Submission::factory()->create(['email' => null, 'phone' => '+992 93 000 00 00']);
    actingAs($editor)
        ->put("/submissions/{$phoneOnly->id}", ['status' => 'completed', 'assigned_to' => null])
        ->assertSessionHas('success', 'Обращение обновлено.');

    Notification::assertNothingSent();
});
