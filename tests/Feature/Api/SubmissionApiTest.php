<?php

use App\Enums\RegionType;
use App\Enums\SubmissionStatus;
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
    $this->postJson('/api/v1/submissions?locale=ru', [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['name', 'phone', 'message', 'consent'])
        ->assertJsonMissingValidationErrors('email')
        ->assertJsonPath('errors.phone.0', 'Укажите телефон или электронную почту.');
});

it('takes a phone number instead of an email', function () {
    // Most people in the regions answer the phone, not mail.
    $this->postJson('/api/v1/submissions', validPayload(['email' => '', 'phone' => '+992 93 123 45 67']))
        ->assertCreated();

    $submission = Submission::query()->firstOrFail();
    expect($submission->phone)->toBe('+992 93 123 45 67')
        ->and($submission->email)->toBeNull();
});

it('asks for a phone or an email in the language of the page', function () {
    $payload = validPayload(['email' => '', 'phone' => '']);

    $this->postJson('/api/v1/submissions?locale=tg', $payload)
        ->assertUnprocessable()
        ->assertJsonPath('errors.phone.0', 'Телефон ё почтаи электрониро нишон диҳед.');

    $this->postJson('/api/v1/submissions', validPayload(['phone' => 'позвоните мне']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('phone');
});

it('stores the region the citizen picked by its code', function () {
    $region = Region::query()->create([
        'name' => ['ru' => 'Хатлонская область'],
        'code' => 'khatlon-submission',
        'type' => RegionType::Oblast,
        'districts_count' => 24,
        'sort' => 1,
    ]);

    $this->postJson('/api/v1/submissions', validPayload(['region' => 'khatlon-submission']))
        ->assertCreated();

    expect(Submission::query()->first()->region_id)->toBe($region->id);

    $this->postJson('/api/v1/submissions', validPayload(['region' => 'atlantis']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('region');
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

it('gives a check code with the number and keeps only its hash', function () {
    $response = $this->postJson('/api/v1/submissions', validPayload())->assertCreated();

    $code = $response->json('access_code');
    expect($code)->toMatch('/^[A-HJ-NP-Z2-9]{4}-[A-HJ-NP-Z2-9]{4}$/');

    $submission = Submission::query()->firstOrFail();
    expect($submission->access_code_hash)->not->toBeNull()
        ->and($submission->access_code_hash)->not->toContain(str_replace('-', '', $code))
        ->and($submission->toArray())->not->toHaveKey('access_code_hash');
});

it('shows the citizen the status by the number and the code', function () {
    $sent = $this->postJson('/api/v1/submissions', validPayload())->json();
    $submission = Submission::query()->firstOrFail();
    $submission->update(['status' => SubmissionStatus::InProgress]);

    // As typed: other case, no dash, the number without the prefix.
    [$year, $number] = array_slice(explode('-', $sent['tracking_number']), 1);
    $this->postJson('/api/v1/submissions/status?locale=ru', [
        'tracking_number' => "{$year} ".ltrim($number, '0'),
        'access_code' => strtolower(str_replace('-', ' ', $sent['access_code'])),
    ])
        ->assertOk()
        ->assertJsonPath('data.tracking_number', $sent['tracking_number'])
        ->assertJsonPath('data.status_code', 'in_review')
        ->assertJsonPath('data.status', 'На рассмотрении')
        ->assertJsonMissingPath('data.name')
        ->assertJsonMissingPath('data.message');

    $this->postJson('/api/v1/submissions/status?locale=tg', [
        'tracking_number' => $sent['tracking_number'],
        'access_code' => $sent['access_code'],
    ])->assertJsonPath('data.status', 'Дар баррасӣ');
});

it('does not open a status by the number alone or tell whether it exists', function () {
    $sent = $this->postJson('/api/v1/submissions', validPayload())->json();

    $wrongCode = $this->postJson('/api/v1/submissions/status?locale=ru', [
        'tracking_number' => $sent['tracking_number'],
        'access_code' => 'AAAA-AAAA',
    ])->assertNotFound();
    $unknown = $this->postJson('/api/v1/submissions/status?locale=ru', [
        'tracking_number' => 'КЧС-2026-99999',
        'access_code' => $sent['access_code'],
    ])->assertNotFound();

    expect($wrongCode->json('message'))->toBe('Обращение не найдено. Проверьте номер и код проверки.')
        ->and($unknown->json())->toBe($wrongCode->json());
});

it('shows spam to the citizen as left without review', function () {
    $sent = $this->postJson('/api/v1/submissions', validPayload())->json();
    Submission::query()->firstOrFail()->update(['status' => SubmissionStatus::Spam]);

    $this->postJson('/api/v1/submissions/status?locale=ru', [
        'tracking_number' => $sent['tracking_number'],
        'access_code' => $sent['access_code'],
    ])->assertOk()->assertJsonPath('data.status_code', 'rejected');
});

it('has no code for appeals sent before codes existed', function () {
    $old = Submission::factory()->create(['access_code_hash' => null]);

    $this->postJson('/api/v1/submissions/status', [
        'tracking_number' => $old->tracking_number,
        'access_code' => 'AAAA-AAAA',
    ])->assertNotFound();
});

it('limits status checks to ten a minute', function () {
    foreach (range(1, 10) as $try) {
        $this->postJson('/api/v1/submissions/status', [
            'tracking_number' => 'КЧС-2026-00001',
            'access_code' => "AAAA-AAA{$try}",
        ])->assertNotFound();
    }

    $this->postJson('/api/v1/submissions/status', [
        'tracking_number' => 'КЧС-2026-00001',
        'access_code' => 'AAAA-AAAB',
    ])->assertTooManyRequests();
});
