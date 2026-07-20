<?php

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

it('validates the required fields', function () {
    $this->postJson('/api/v1/submissions', [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['name', 'email', 'message', 'consent']);
});

it('rate-limits repeated submissions from the same client', function () {
    for ($i = 0; $i < 10; $i++) {
        $this->postJson('/api/v1/submissions', validPayload())->assertCreated();
    }

    $this->postJson('/api/v1/submissions', validPayload())->assertStatus(429);
});
