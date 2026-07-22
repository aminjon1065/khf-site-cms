<?php

use App\Enums\RegionType;
use App\Models\Region;
use App\Models\Submission;

function validPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Иван Гражданинов',
        'email' => 'ivan@example.com',
        'topic' => 'Вопрос о деятельности Комитета',
        'message' => 'Прошу рассмотреть моё обращение по существу.',
        'consent' => true,
    ], $overrides);
}

it('accepts a valid submission and returns a tracking number', function () {
    $response = $this->postJson('/api/v1/submissions', validPayload());

    $response->assertCreated();
    expect($response->json('tracking_number'))->toStartWith('КЧС-')
        ->and(Submission::query()->count())->toBe(1);

    $submission = Submission::query()->first();
    expect($submission->status->value)->toBe('new')
        ->and($submission->tracking_number)->not->toBeNull()
        ->and($submission->ip_address)->not->toBeNull();
});

it('silently drops honeypot submissions without storing them', function () {
    $response = $this->postJson('/api/v1/submissions', validPayload([
        'website' => 'http://spam.example',
    ]));

    $response->assertCreated();
    expect($response->json('tracking_number'))->toStartWith('КЧС-')
        ->and(Submission::query()->count())->toBe(0);
});

it('requires consent', function () {
    $this->postJson('/api/v1/submissions', validPayload(['consent' => false]))
        ->assertStatus(422)
        ->assertJsonValidationErrors('consent');
});

it('validates and stores the selected region', function () {
    $region = Region::query()->create([
        'name' => ['ru' => 'Согдийская область'],
        'code' => 'sughd-submission',
        'type' => RegionType::Oblast,
        'districts_count' => 18,
        'sort' => 1,
    ]);

    $this->postJson('/api/v1/submissions', validPayload(['region_id' => $region->id]))
        ->assertCreated();

    expect(Submission::query()->first()->region_id)->toBe($region->id);

    $this->postJson('/api/v1/submissions', validPayload(['region_id' => 999999]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('region_id');
});

it('validates the required fields', function () {
    $this->postJson('/api/v1/submissions', [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['name', 'email', 'message', 'consent']);
});

it('localizes public validation errors', function () {
    $this->postJson('/api/v1/submissions?locale=en', [])
        ->assertUnprocessable()
        ->assertJsonPath('errors.name.0', 'Enter your name.');

    $this->postJson('/api/v1/submissions?locale=tg', [])
        ->assertUnprocessable()
        ->assertJsonPath('errors.name.0', 'Ному насабро ворид кунед.');
});

it('rate-limits repeated submissions from the same client', function () {
    for ($i = 0; $i < 10; $i++) {
        $this->postJson('/api/v1/submissions', validPayload())->assertCreated();
    }

    $this->postJson('/api/v1/submissions', validPayload())->assertStatus(429);
});
