<?php

use App\Models\Announcement;
use App\Models\Instruction;

// Полнота перевода — не отчётный показатель, а условие публикации: при
// значении меньше 100% PublicationChecklist не даёт опубликовать материал.
// Поэтому каждое новое переводимое поле по умолчанию поднимает планку, и
// необязательные поля обязаны быть исключены явно. Иначе добавление поля
// молча запрещает редактору публиковать материал, пока он не заполнит это
// поле на всех языках, — и ошибка выглядит как поломка публикации.

it('does not count an optional field towards completeness', function () {
    $instruction = Instruction::factory()->make([
        'name' => ['ru' => 'Землетрясение', 'tg' => 'Заминҷунбӣ', 'en' => 'Earthquake'],
        'summary' => ['ru' => 'Описание', 'tg' => 'Тавсиф', 'en' => 'Summary'],
        'body' => ['ru' => 'Текст', 'tg' => 'Матн', 'en' => 'Body'],
        'key_point' => ['ru' => '', 'tg' => '', 'en' => ''],
    ]);

    expect($instruction->languageCompleteness())
        ->toMatchArray(['ru' => 100, 'tg' => 100, 'en' => 100]);
});

it('still reaches 100% when the optional field is filled', function () {
    $instruction = Instruction::factory()->make([
        'name' => ['ru' => 'Землетрясение', 'tg' => 'Заминҷунбӣ', 'en' => 'Earthquake'],
        'summary' => ['ru' => 'Описание', 'tg' => 'Тавсиф', 'en' => 'Summary'],
        'body' => ['ru' => 'Текст', 'tg' => 'Матн', 'en' => 'Body'],
        'key_point' => ['ru' => 'Присядьте', 'tg' => 'Нишинед', 'en' => 'Drop'],
    ]);

    expect($instruction->languageCompleteness())
        ->toMatchArray(['ru' => 100, 'tg' => 100, 'en' => 100]);
});

it('still counts the required fields', function () {
    // Исключение касается только помеченных полей: пропуск обязательного
    // по-прежнему опускает полноту и блокирует публикацию.
    $instruction = Instruction::factory()->make([
        'name' => ['ru' => 'Землетрясение', 'tg' => '', 'en' => ''],
        'summary' => ['ru' => 'Описание', 'tg' => '', 'en' => ''],
        'body' => ['ru' => '', 'tg' => '', 'en' => ''],
        'key_point' => ['ru' => 'Присядьте', 'tg' => 'Нишинед', 'en' => 'Drop'],
    ]);

    expect($instruction->languageCompleteness()['ru'])->toBe(67)
        ->and($instruction->languageCompleteness()['tg'])->toBe(0);
});

it('counts every translatable field of a model without exclusions', function () {
    // Announcement исключений не объявляет и использует расчёт трейта, поэтому
    // полнота обязана считаться по всем её переводимым полям: заполнены все —
    // 100%, пропущено одно — меньше 100%, то есть публикация блокируется.
    //
    // News сюда не годится: она переопределяет languageCompleteness() и
    // считает ещё и SEO, то есть расчёт трейта на неё не влияет.
    $announcement = Announcement::factory()->make();
    $fields = $announcement->getTranslatableAttributes();

    expect($fields)->not->toBeEmpty();

    foreach ($fields as $field) {
        $announcement->setTranslation($field, 'ru', 'текст');
        $announcement->setTranslation($field, 'tg', 'матн');
        $announcement->setTranslation($field, 'en', 'text');
    }

    expect($announcement->languageCompleteness())
        ->toMatchArray(['ru' => 100, 'tg' => 100, 'en' => 100]);

    $announcement->setTranslation($fields[0], 'tg', '');

    expect($announcement->languageCompleteness()['tg'])->toBeLessThan(100)
        ->and($announcement->languageCompleteness()['ru'])->toBe(100);
});
