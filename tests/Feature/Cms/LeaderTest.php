<?php

use App\Models\Activity;
use App\Models\Leader;
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

function leaderUser(string $role): User
{
    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

it('lets an admin open the leadership list', function () {
    actingAs(leaderUser('admin'))->get('/leadership')->assertOk();
});

it('lets a chief editor view the leadership list (view-only grant)', function () {
    actingAs(leaderUser('chief_editor'))->get('/leadership')->assertOk();
});

it('forbids a role without leadership access', function () {
    actingAs(leaderUser('editor'))->get('/leadership')->assertForbidden();
});

it('forbids a view-only role from creating a leader', function () {
    actingAs(leaderUser('chief_editor'))->post('/leadership', [
        'role' => ['ru' => 'Взлом'],
        'name' => ['ru' => 'Взлом'],
    ])->assertForbidden();
});

it('creates a deputy', function () {
    actingAs(leaderUser('admin'))->post('/leadership', [
        'role' => ['ru' => 'Заместитель председателя', 'tg' => 'Муовини раис'],
        'name' => ['ru' => 'Тестов Тест Тестович'],
        'meta' => ['ru' => 'Полковник'],
        'bio' => ['ru' => 'Отвечает за тестирование.'],
        'sort' => 5,
    ])->assertRedirect('/leadership');

    $leader = Leader::query()->where('name->ru', 'Тестов Тест Тестович')->first();

    expect($leader)->not->toBeNull()
        ->and($leader->getTranslation('role', 'ru'))->toBe('Заместитель председателя')
        ->and($leader->getTranslation('role', 'tg'))->toBe('Муовини раис')
        ->and($leader->is_chairman)->toBeFalse()
        ->and($leader->sort)->toBe(5)
        ->and(Activity::query()->where('log_name', 'leaders')->where('subject_id', $leader->id)->exists())->toBeTrue();
});

it('rejects a leader without a Russian role or name', function () {
    actingAs(leaderUser('admin'))->post('/leadership', [
        'role' => ['ru' => ''],
        'name' => ['ru' => ''],
    ])->assertSessionHasErrors(['role.ru', 'name.ru']);

    expect(Leader::query()->count())->toBe(0);
});

it('promoting a new chairman demotes the previous one', function () {
    $old = Leader::factory()->chairman()->create(['name' => ['ru' => 'Старый председатель']]);

    actingAs(leaderUser('admin'))->post('/leadership', [
        'role' => ['ru' => 'Председатель Комитета'],
        'name' => ['ru' => 'Новый председатель'],
        'is_chairman' => true,
    ])->assertRedirect('/leadership');

    expect($old->refresh()->is_chairman)->toBeFalse()
        ->and(Leader::query()->where('is_chairman', true)->count())->toBe(1)
        ->and(Leader::query()->where('is_chairman', true)->first()->getTranslation('name', 'ru'))->toBe('Новый председатель');
});

it('promoting an existing deputy to chairman on update demotes the previous one', function () {
    $chairman = Leader::factory()->chairman()->create();
    $deputy = Leader::factory()->create(['is_chairman' => false]);

    actingAs(leaderUser('admin'))->put("/leadership/{$deputy->id}", [
        'role' => $deputy->getTranslations('role'),
        'name' => $deputy->getTranslations('name'),
        'is_chairman' => true,
    ])->assertRedirect('/leadership');

    expect($chairman->refresh()->is_chairman)->toBeFalse()
        ->and($deputy->refresh()->is_chairman)->toBeTrue();
});

it('uploads a photo for a leader', function () {
    Storage::fake('public');

    $leader = Leader::factory()->create();

    actingAs(leaderUser('admin'))->put("/leadership/{$leader->id}", [
        'role' => $leader->getTranslations('role'),
        'name' => $leader->getTranslations('name'),
        'photo' => UploadedFile::fake()->image('chairman.jpg'),
    ])->assertRedirect('/leadership');

    expect($leader->refresh()->getFirstMedia('photo'))->not->toBeNull();
});

it('picks a photo from the media library', function () {
    Storage::fake('public');
    $editor = leaderUser('admin');

    actingAs($editor)->post('/media', ['file' => UploadedFile::fake()->image('lib.jpg')]);
    $sourceId = Media::query()->latest('id')->firstOrFail()->id;

    $leader = Leader::factory()->create();

    actingAs($editor)->put("/leadership/{$leader->id}", [
        'role' => $leader->getTranslations('role'),
        'name' => $leader->getTranslations('name'),
        'photo_media_id' => $sourceId,
    ])->assertRedirect('/leadership');

    expect($leader->refresh()->getFirstMedia('photo'))->not->toBeNull();
});

it('removes an existing photo', function () {
    Storage::fake('public');

    $leader = Leader::factory()->create();
    $leader->addMedia(UploadedFile::fake()->image('old.jpg'))->toMediaCollection('photo');

    actingAs(leaderUser('admin'))->put("/leadership/{$leader->id}", [
        'role' => $leader->getTranslations('role'),
        'name' => $leader->getTranslations('name'),
        'photo_remove' => true,
    ])->assertRedirect('/leadership');

    expect($leader->refresh()->getFirstMedia('photo'))->toBeNull();
});

it('deletes a leader', function () {
    $leader = Leader::factory()->create();

    actingAs(leaderUser('admin'))->delete("/leadership/{$leader->id}")->assertRedirect('/leadership');

    expect(Leader::query()->find($leader->id))->toBeNull();
});

it('forbids a view-only role from deleting a leader', function () {
    $leader = Leader::factory()->create();

    actingAs(leaderUser('chief_editor'))->delete("/leadership/{$leader->id}")->assertForbidden();

    expect(Leader::query()->find($leader->id))->not->toBeNull();
});
