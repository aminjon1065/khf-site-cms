<?php

use App\Enums\ContentStatus;
use App\Models\Instruction;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

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

it('stores and sanitises the optional rich-text body', function () {
    actingAs(instrUser('editor'))->post('/instructions', [
        'name' => ['ru' => 'Инструкция с описанием', 'tg' => '', 'en' => ''],
        'body' => [
            'ru' => '<h2>Подробности</h2><p>Текст <b>важно</b><script>alert(1)</script></p>'
                .'<a href="https://khf.tj" onclick="steal()">документ</a>',
            'tg' => '',
            'en' => '',
        ],
        'action' => 'draft',
    ])->assertRedirect('/instructions');

    $body = Instruction::query()->first()->getTranslation('body', 'ru');

    expect($body)
        ->toContain('<h2>Подробности</h2>')
        ->toContain('<b>важно</b>')
        ->not->toContain('<script')
        ->not->toContain('onclick');
});

it('sets the instruction image from a media-library asset', function () {
    Storage::fake('public');
    $editor = instrUser('editor');

    actingAs($editor)->post('/media', ['file' => UploadedFile::fake()->image('lib.jpg')]);
    $sourceId = Media::query()->latest('id')->firstOrFail()->id;

    actingAs($editor)->post('/instructions', [
        'name' => ['ru' => 'Инструкция с картинкой из медиатеки', 'tg' => '', 'en' => ''],
        'image_media_id' => $sourceId,
        'action' => 'draft',
    ])->assertRedirect('/instructions');

    expect(Instruction::query()->first()->getFirstMedia('image'))->not->toBeNull();
});

it('stays on the editor when saving a draft with the stay flag (Ctrl+S)', function () {
    $response = actingAs(instrUser('editor'))->post('/instructions', [
        'name' => ['ru' => 'Черновик со stay', 'tg' => '', 'en' => ''],
        'action' => 'draft',
        'stay' => true,
    ]);

    $instruction = Instruction::query()->firstOrFail();
    $response->assertRedirect("/instructions/{$instruction->id}/edit");
});
