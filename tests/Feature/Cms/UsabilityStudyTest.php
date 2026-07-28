<?php

use App\Models\UsabilitySession;
use App\Models\User;
use App\Services\UsabilityReportService;
use App\Support\UsabilityStudy;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\seed;

beforeEach(function () {
    seed(RolePermissionSeeder::class);
    app(UsabilityReportService::class)->forget();
});

function usabilityUser(string $role): User
{
    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

/**
 * @return array<string, mixed>
 */
function validUsabilityPayload(): array
{
    $tasks = [];

    foreach (UsabilityStudy::TASKS as $key => $definition) {
        $tasks[$key] = [
            'completed' => true,
            'assisted' => false,
            'duration_seconds' => $definition['target_seconds'] ?? 90,
            'irreversible_error' => false,
        ];
    }

    return [
        'participant_code' => 'UXT-001',
        'role' => 'editor',
        'experience_level' => 'none',
        'tasks' => $tasks,
        'sus_responses' => [5, 1, 5, 1, 5, 1, 5, 1, 5, 1],
        'notes' => 'Участник завершил сценарий без подсказок.',
        'started_at' => now()->subMinutes(20)->toIso8601String(),
        'completed_at' => now()->toIso8601String(),
    ];
}

it('renders the anonymous field study for authorized facilitators', function () {
    actingAs(usabilityUser('admin'))->get('/usability')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('usability/index')
            ->has('tasks', 8)
            ->where('summary.participants', 0)
            ->where('summary.ready', false)
            ->has('targets', 7)
            ->has('task_metrics', 8)
            ->has('sessions', 0));
});

it('forbids users without users view permission', function () {
    actingAs(usabilityUser('editor'))->get('/usability')->assertForbidden();
    actingAs(usabilityUser('editor'))->post('/usability', validUsabilityPayload())->assertForbidden();
});

it('stores a complete anonymous session and calculates standard SUS', function () {
    $facilitator = usabilityUser('admin');

    actingAs($facilitator)
        ->from('/usability')
        ->post('/usability', validUsabilityPayload())
        ->assertRedirect('/usability')
        ->assertSessionHas('success');

    $session = UsabilitySession::query()->sole();

    expect($session)
        ->participant_code->toBe('UXT-001')
        ->sus_score->toBe(100.0)
        ->facilitator_id->toBe($facilitator->id)
        ->and($session->tasks)->toHaveCount(8);
});

it('requires the exact task contract and ten valid SUS answers', function () {
    $payload = validUsabilityPayload();
    unset($payload['tasks']['alt_fix']);
    $payload['sus_responses'][4] = 6;

    actingAs(usabilityUser('admin'))
        ->post('/usability', $payload)
        ->assertSessionHasErrors(['tasks', 'sus_responses.4']);

    expect(UsabilitySession::query()->count())->toBe(0);
});

it('passes the UX-10 gate only when every target is reached', function () {
    UsabilitySession::factory()->count(5)->create();

    $report = app(UsabilityReportService::class);
    $report->forget();
    $result = $report->report();

    expect($result['summary'])
        ->participants->toBe(5)
        ->unassisted_success_rate->toBe(100.0)
        ->sus_average->toBe(100.0)
        ->irreversible_errors->toBe(0)
        ->ready->toBeTrue()
        ->and($result['targets'])
        ->each(fn ($target) => $target->passed->toBeTrue());
});

it('fails the gate after assisted work or an irreversible error', function () {
    UsabilitySession::factory()->count(4)->create();
    $tasks = UsabilitySession::factory()->make()->tasks;
    $tasks['news_first']['assisted'] = true;
    $tasks['trash_restore']['irreversible_error'] = true;
    UsabilitySession::factory()->create(['tasks' => $tasks]);

    $report = app(UsabilityReportService::class);
    $report->forget();
    $result = $report->report();

    expect($result['summary'])
        ->ready->toBeFalse()
        ->irreversible_errors->toBe(1)
        ->and($result['targets']['irreversible_errors']['passed'])->toBeFalse();
});

it('does not persist participant names or contact details', function () {
    expect(Schema::getColumnListing('usability_sessions'))
        ->not->toContain('name', 'email', 'phone', 'ip_address', 'user_agent');
});

it('keeps the facilitator interface semantically structured', function () {
    $source = file_get_contents(resource_path('js/pages/usability/index.tsx'));

    expect($source)->toContain(
        '<form onSubmit={submit}',
        '<fieldset',
        '<legend',
        '<caption className="sr-only">',
        'scope="col"',
        'scope="row"',
        'aria-labelledby="usability-results-heading"',
        'aria-labelledby="usability-session-heading"',
    )->and(substr_count($source, 'className="min-h-11!"'))->toBe(8)
        ->and(substr_count($source, 'className="min-h-11"'))->toBe(3);
});
