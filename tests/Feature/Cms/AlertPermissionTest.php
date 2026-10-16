<?php

use App\Enums\ContentStatus;
use App\Enums\Severity;
use App\Models\Alert;
use App\Models\Region;
use App\Models\User;
use App\Services\WorkflowService;
use Database\Seeders\RegionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\seed;

beforeEach(function () {
    seed([RolePermissionSeeder::class, RegionSeeder::class]);
});

/**
 * @param  int|null  $limitedTo  the region the account is limited to
 */
function userWithRole(string $role, ?int $limitedTo = null): User
{
    return giveRole(User::factory()->create([
        'region_id' => $limitedTo,
        'limited_to_region' => $limitedTo !== null,
    ]), $role);
}

it('lets an editor open the alert wizard', function () {
    actingAs(userWithRole('editor'))->get('/alerts/create')->assertOk();
});

it('forbids a viewer from creating an alert', function () {
    actingAs(userWithRole('viewer'))->get('/alerts/create')->assertForbidden();
});

it('forbids a translator from deleting an alert', function () {
    $alert = Alert::factory()->create();
    actingAs(userWithRole('translator'))->delete("/alerts/{$alert->id}")->assertForbidden();
});

it('lets a chief editor create an alert draft', function () {
    $region = Region::query()->where('code', 'khatlon')->first();

    actingAs(userWithRole('chief_editor'))
        ->post('/alerts', [
            'internal_title' => 'Тестовое предупреждение',
            'hazard_type' => 'mudflow',
            'severity' => 'warning',
            'territory_type' => 'regions',
            'regions' => [$region->id],
            'title' => ['ru' => 'Заголовок'],
            'action' => 'draft',
        ])
        ->assertRedirect('/alerts');

    expect(Alert::query()->where('internal_title', 'Тестовое предупреждение')->exists())->toBeTrue();
});

it('confines an editor limited to a region to its alerts', function () {
    $khatlon = Region::query()->where('code', 'khatlon')->first();
    $sughd = Region::query()->where('code', 'sughd')->first();

    $regional = userWithRole('editor', $khatlon->id);

    $inRegion = Alert::factory()->create();
    $inRegion->regions()->attach($khatlon->id);

    $outOfRegion = Alert::factory()->create();
    $outOfRegion->regions()->attach($sughd->id);

    actingAs($regional)->put("/alerts/{$outOfRegion->id}", [
        'internal_title' => $outOfRegion->internal_title,
        'hazard_type' => $outOfRegion->hazard_type->value,
        'severity' => $outOfRegion->severity->value,
        'territory_type' => 'regions',
    ])->assertForbidden();

    actingAs($regional)->get('/alerts')->assertOk();
});

it('rejects country-wide and foreign-region alerts from an editor limited to a region', function () {
    $khatlon = Region::query()->where('code', 'khatlon')->firstOrFail();
    $sughd = Region::query()->where('code', 'sughd')->firstOrFail();
    $regional = userWithRole('editor', $khatlon->id);

    actingAs($regional)->post('/alerts', [
        'internal_title' => 'Общенациональное предупреждение',
        'hazard_type' => 'mudflow',
        'severity' => 'warning',
        'territory_type' => 'country',
        'regions' => [],
        'title' => ['ru' => 'Заголовок'],
        'action' => 'draft',
    ])->assertSessionHasErrors('regions');

    actingAs($regional)->post('/alerts', [
        'internal_title' => 'Чужой регион',
        'hazard_type' => 'mudflow',
        'severity' => 'warning',
        'territory_type' => 'regions',
        'regions' => [$sughd->id],
        'title' => ['ru' => 'Заголовок'],
        'action' => 'draft',
    ])->assertSessionHasErrors('regions');

    expect(Alert::query()->count())->toBe(0);
});

it('prevents an editor limited to a region from moving an alert to another region', function () {
    $khatlon = Region::query()->where('code', 'khatlon')->firstOrFail();
    $sughd = Region::query()->where('code', 'sughd')->firstOrFail();
    $regional = userWithRole('editor', $khatlon->id);
    $alert = Alert::factory()->create(['author_id' => $regional->id]);
    $alert->regions()->attach($khatlon);

    actingAs($regional)->put("/alerts/{$alert->id}", [
        'internal_title' => $alert->internal_title,
        'hazard_type' => $alert->hazard_type->value,
        'severity' => $alert->severity->value,
        'territory_type' => 'regions',
        'regions' => [$sughd->id],
        'action' => 'draft',
    ])->assertSessionHasErrors('regions');

    expect($alert->regions()->pluck('regions.id')->all())->toBe([$khatlon->id]);
});

it('requires a future publication time for a scheduled alert', function () {
    $region = Region::query()->where('code', 'khatlon')->firstOrFail();

    actingAs(userWithRole('chief_editor'))->post('/alerts', [
        'internal_title' => 'Плановая публикация',
        'hazard_type' => 'mudflow',
        'severity' => 'warning',
        'territory_type' => 'regions',
        'regions' => [$region->id],
        'title' => ['ru' => 'Заголовок'],
        'action' => 'submit',
        'publish_mode' => 'schedule',
    ])->assertSessionHasErrors('scheduled_at');

    actingAs(userWithRole('chief_editor'))->post('/alerts', [
        'internal_title' => 'Плановая публикация',
        'hazard_type' => 'mudflow',
        'severity' => 'warning',
        'territory_type' => 'regions',
        'regions' => [$region->id],
        'title' => ['ru' => 'Заголовок'],
        'action' => 'submit',
        'publish_mode' => 'schedule',
        'scheduled_at' => now()->subMinute()->toDateTimeString(),
    ])->assertSessionHasErrors('scheduled_at');
});

it('sends an alert scheduled by someone who cannot publish to approval', function () {
    $region = Region::query()->where('code', 'khatlon')->firstOrFail();

    actingAs(userWithRole('editor'))->post('/alerts', [
        'internal_title' => 'Плановое предупреждение редактора',
        'hazard_type' => 'mudflow',
        'severity' => 'warning',
        'territory_type' => 'regions',
        'regions' => [$region->id],
        'title' => ['ru' => 'Заголовок'],
        'action' => 'submit',
        'publish_mode' => 'schedule',
        'scheduled_at' => now()->addDay()->toDateTimeString(),
    ])
        ->assertRedirect('/alerts')
        ->assertSessionHas('success', 'Предупреждение отправлено на согласование.');

    expect(Alert::query()->where('internal_title', 'Плановое предупреждение редактора')->sole()->status)
        ->toBe(ContentStatus::Review);
});

it('checks who may release a critical alert when it is scheduled', function () {
    $alert = Alert::factory()->create([
        'severity' => Severity::Critical,
        'status' => ContentStatus::Draft,
    ]);
    // Publishes alerts, but isn't trusted to approve them.
    $publisher = userWithRole(customRole('alert_publisher', [
        'alerts.view', 'alerts.create', 'alerts.edit', 'alerts.publish',
    ]));

    expect(fn () => app(WorkflowService::class)->transition($alert, ContentStatus::Scheduled, $publisher))
        ->toThrow(ValidationException::class);

    expect($alert->fresh()->status)->toBe(ContentStatus::Draft);
});

it('lets a chief editor release a critical alert', function () {
    $alert = Alert::factory()->create([
        'severity' => Severity::Critical,
        'status' => ContentStatus::Draft,
    ]);

    app(WorkflowService::class)->transition($alert, ContentStatus::Scheduled, userWithRole('chief_editor'));

    expect($alert->fresh()->status)->toBe(ContentStatus::Scheduled);
});

it('offers as approvers everyone who may approve alerts, whatever their role is called', function () {
    $chief = userWithRole('chief_editor');
    $dutyOfficer = userWithRole(customRole('duty_officer', ['alerts.view', 'alerts.approve']));
    $editor = userWithRole('editor');

    actingAs($editor)->get('/alerts/create')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where(
            'reference.approvers',
            fn ($approvers): bool => collect($approvers)->pluck('id')->sort()->values()->all()
                === collect([$chief->id, $dutyOfficer->id])->sort()->values()->all(),
        ));
});

it('does not limit an editor whose account only names a region', function () {
    $khatlon = Region::query()->where('code', 'khatlon')->firstOrFail();
    $sughd = Region::query()->where('code', 'sughd')->firstOrFail();
    $editor = giveRole(User::factory()->create(['region_id' => $khatlon->id]), 'editor');
    $alert = Alert::factory()->create();
    $alert->regions()->attach($sughd);

    expect($editor->isLimitedToRegion())->toBeFalse()
        ->and($editor->can('update', $alert))->toBeTrue();
});

it('sorts the alert list by escalation, not by the alphabet of the level code', function () {
    foreach (['warning', 'critical', 'info', 'danger', 'attention'] as $severity) {
        Alert::factory()->create(['severity' => $severity]);
    }

    $order = fn (string $dir): array => actingAs(userWithRole('chief_editor'))
        ->get("/alerts?view=all&sort=severity&dir={$dir}")
        ->assertOk()
        ->inertiaProps('alerts');

    expect(array_column($order('desc'), 'severity'))->toBe(['critical', 'danger', 'warning', 'attention', 'info'])
        ->and(array_column($order('asc'), 'severity'))->toBe(['info', 'attention', 'warning', 'danger', 'critical']);
});

it('keeps the operator in the wizard when they save a draft', function () {
    $region = Region::query()->where('code', 'khatlon')->first();
    $operator = userWithRole('chief_editor');
    $payload = [
        'internal_title' => 'Черновик из мастера',
        'hazard_type' => 'mudflow',
        'severity' => 'warning',
        'territory_type' => 'regions',
        'regions' => [$region->id],
        'title' => ['ru' => 'Заголовок'],
        'action' => 'draft',
        'stay' => true,
    ];

    $response = actingAs($operator)->post('/alerts', $payload);
    $alert = Alert::query()->where('internal_title', 'Черновик из мастера')->firstOrFail();
    $response->assertRedirect("/alerts/{$alert->id}/edit");

    actingAs($operator)
        ->put("/alerts/{$alert->id}", [...$payload, 'internal_title' => 'Черновик, шаг 3'])
        ->assertRedirect("/alerts/{$alert->id}/edit");

    expect($alert->fresh()->internal_title)->toBe('Черновик, шаг 3')
        ->and($alert->fresh()->status)->toBe(ContentStatus::Draft);
});

it('saves a country-wide alert the way the wizard sends it', function () {
    // The wizard always sends the regions list, empty for the whole country.
    // `min:1` on it rejected every country-wide alert.
    actingAs(userWithRole('chief_editor'))
        ->post('/alerts', [
            'internal_title' => 'Общенациональное предупреждение',
            'hazard_type' => 'earthquake',
            'severity' => 'danger',
            'territory_type' => 'country',
            'regions' => [],
            'districts' => [],
            'title' => ['ru' => 'Заголовок'],
            'action' => 'draft',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect('/alerts');

    expect(Alert::query()->where('internal_title', 'Общенациональное предупреждение')->value('territory_type'))
        ->toBe('country');
});

it('still asks for a region when the alert covers separate regions', function () {
    actingAs(userWithRole('chief_editor'))
        ->post('/alerts', [
            'internal_title' => 'Без регионов',
            'hazard_type' => 'mudflow',
            'severity' => 'warning',
            'territory_type' => 'regions',
            'regions' => [],
            'title' => ['ru' => 'Заголовок'],
            'action' => 'draft',
        ])
        ->assertSessionHasErrors('regions');
});

it('saves an alert with languages left empty, as the wizard sends them', function () {
    // The wizard sends every language of every text field; an empty one
    // arrives as null (ConvertEmptyStringsToNull) and used to crash the save.
    actingAs(userWithRole('chief_editor'))
        ->post('/alerts', [
            'internal_title' => 'Только русский и таджикский',
            'hazard_type' => 'flood',
            'severity' => 'warning',
            'territory_type' => 'country',
            'regions' => [],
            'title' => ['ru' => 'Паводок', 'tg' => 'Обхезӣ', 'en' => ''],
            'summary' => ['ru' => '', 'tg' => '', 'en' => ''],
            'contacts' => ['ru' => '112', 'tg' => '', 'en' => ''],
            'action' => 'draft',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect('/alerts');

    $alert = Alert::query()->where('internal_title', 'Только русский и таджикский')->firstOrFail();

    expect($alert->getTranslations('title'))->toEqual(['ru' => 'Паводок', 'tg' => 'Обхезӣ'])
        ->and($alert->getTranslations('summary'))->toBe([])
        ->and($alert->getTranslations('contacts'))->toEqual(['ru' => '112']);
});

it('saves the description of the area in each language', function () {
    $region = Region::query()->where('code', 'rrp')->firstOrFail();

    actingAs(userWithRole('chief_editor'))
        ->post('/alerts', [
            'internal_title' => 'Лавина на трассе',
            'hazard_type' => 'avalanche',
            'severity' => 'danger',
            'territory_type' => 'regions',
            'regions' => [$region->id],
            'territory_note' => ['ru' => 'Трасса Душанбе — Чанак, 45–72 км', 'tg' => '', 'en' => ''],
            'title' => ['ru' => 'Лавинная опасность'],
            'action' => 'draft',
        ])
        ->assertSessionHasNoErrors();

    expect(Alert::query()->where('internal_title', 'Лавина на трассе')->firstOrFail()->getTranslations('territory_note'))
        ->toEqual(['ru' => 'Трасса Душанбе — Чанак, 45–72 км']);
});
