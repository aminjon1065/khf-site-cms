<?php

use App\Enums\ContentStatus;
use App\Models\Instruction;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\seed;

beforeEach(function () {
    seed(RolePermissionSeeder::class);
});

function instrUser(string $role): User
{
    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

it('lets an editor open the instruction create form', function () {
    actingAs(instrUser('editor'))->get('/instructions/create')->assertOk();
});

it('forbids a viewer from opening the create form', function () {
    actingAs(instrUser('viewer'))->get('/instructions/create')->assertForbidden();
});

it('creates a draft with cleaned sections and an auto slug', function () {
    actingAs(instrUser('editor'))->post('/instructions', [
        'name' => ['ru' => 'Действия при землетрясении', 'tg' => '', 'en' => ''],
        'sections' => [
            'before' => ['ru' => ['Соберите чемоданчик', '  ']], // blank step dropped
            'during' => ['ru' => ['Укройтесь под столом']],
            'prohibited' => ['ru' => ['Не пользуйтесь лифтом']],
        ],
        'action' => 'draft',
    ])->assertRedirect('/instructions');

    $instruction = Instruction::query()->first();

    expect($instruction)->not->toBeNull()
        ->and($instruction->slug)->not->toBeEmpty()
        ->and($instruction->status)->toBe(ContentStatus::Draft)
        ->and($instruction->sections['before']['ru'])->toBe(['Соберите чемоданчик'])
        ->and($instruction->sections['during']['ru'])->toBe(['Укройтесь под столом']);
});

it('requires a russian name', function () {
    actingAs(instrUser('editor'))->post('/instructions', [
        'name' => ['ru' => '', 'tg' => 'Ягон чиз'],
        'action' => 'draft',
    ])->assertSessionHasErrors('name.ru');
});

it('sends an instruction to review when an editor submits', function () {
    actingAs(instrUser('editor'))->post('/instructions', [
        'name' => ['ru' => 'Памятка на согласование', 'tg' => '', 'en' => ''],
        'action' => 'submit',
        'publish_mode' => 'review',
    ])->assertRedirect('/instructions');

    expect(Instruction::query()->first()->status)->toBe(ContentStatus::Review);
});

it('publishes via the endpoint and the instruction becomes public', function () {
    $instruction = Instruction::factory()->create([
        'slug' => 'guide-visible',
        'name' => ['ru' => 'Видна в API', 'tg' => '', 'en' => ''],
    ]);

    actingAs(instrUser('chief_editor'))->post("/instructions/{$instruction->id}/publish")->assertRedirect();

    expect($instruction->fresh()->status)->toBe(ContentStatus::Published);

    $this->getJson('/api/v1/instructions/guide-visible')
        ->assertOk()
        ->assertJsonPath('data.title', 'Видна в API');
});

it('forbids a viewer from deleting an instruction', function () {
    $instruction = Instruction::factory()->create();

    actingAs(instrUser('viewer'))->delete("/instructions/{$instruction->id}")->assertForbidden();
});

it('soft-deletes an instruction for an authorized user', function () {
    $instruction = Instruction::factory()->create();

    // Only admin/superadmin hold instructions.delete in the permission matrix.
    actingAs(instrUser('admin'))->delete("/instructions/{$instruction->id}")->assertRedirect();

    expect(Instruction::query()->find($instruction->id))->toBeNull()
        ->and(Instruction::withTrashed()->find($instruction->id))->not->toBeNull();
});
