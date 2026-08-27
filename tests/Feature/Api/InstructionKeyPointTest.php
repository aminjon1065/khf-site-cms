<?php

use App\Models\Instruction;

// Блок «Главное за 10 секунд» на странице инструкции существовал, но под этим
// заголовком выводился `summary` — однострочное описание темы из каталожной
// плитки. Для человека в опасности это разные тексты: «Как действовать при
// угрозе селевого потока» не говорит, что делать прямо сейчас.

it('returns the key point on the detail response', function () {
    $instruction = Instruction::factory()->published()->create([
        'summary' => ['ru' => 'Как действовать при угрозе селевого потока', 'tg' => '', 'en' => ''],
        'key_point' => ['ru' => 'Уходите вверх по склону, а не вдоль русла', 'tg' => '', 'en' => ''],
    ]);

    $response = $this->getJson("/api/v1/instructions/{$instruction->slug}?locale=ru")
        ->assertOk();

    expect($response->json('data.key_point'))
        ->toBe('Уходите вверх по склону, а не вдоль русла')
        // Описание остаётся отдельным полем и не подменяется главным.
        ->and($response->json('data.summary'))
        ->toBe('Как действовать при угрозе селевого потока');
});

it('leaves the key point null when the editor did not fill it', function () {
    // Публичная часть в этом случае не выводит блок, а не подставляет summary.
    $instruction = Instruction::factory()->published()->create([
        'summary' => ['ru' => 'Описание темы', 'tg' => '', 'en' => ''],
        'key_point' => ['ru' => '', 'tg' => '', 'en' => ''],
    ]);

    $response = $this->getJson("/api/v1/instructions/{$instruction->slug}?locale=ru")
        ->assertOk();

    expect($response->json('data.key_point'))->toBeNull();
});

it('does not leak the key point of another language', function () {
    // Инструкция по спасению жизни: показать русский текст на таджикской
    // версии страницы недопустимо — человек его может не прочитать.
    $instruction = Instruction::factory()->published()->create([
        'name' => ['ru' => 'Сель', 'tg' => 'Сел', 'en' => ''],
        'key_point' => ['ru' => 'Уходите вверх по склону', 'tg' => '', 'en' => ''],
    ]);

    $response = $this->getJson("/api/v1/instructions/{$instruction->slug}?locale=tg")
        ->assertOk();

    expect($response->json('data.key_point'))->toBeNull();
});

it('omits the key point from the catalog listing', function () {
    // В списке блок не выводится, поэтому и передавать текст незачем.
    Instruction::factory()->published()->create([
        'key_point' => ['ru' => 'Уходите вверх по склону', 'tg' => '', 'en' => ''],
    ]);

    $response = $this->getJson('/api/v1/instructions?locale=ru')->assertOk();

    expect($response->json('data.0'))->not->toHaveKey('key_point');
});
