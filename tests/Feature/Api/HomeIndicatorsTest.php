<?php

use App\Models\HomeBlock;

// Ключевые показатели ведомства. Раньше публичная часть выводила их из
// словаря — «247 спасательных операций», «86 500 обучено» — и эти числа не
// менялись ни при каких данных, выдавая статистику за актуальную.
//
// Считать их система не может: «спасательных операций» и «человек спасено»
// нет ни в одной таблице, цифры приходят из отчётности. Поэтому их вводит
// редактор в настройках блока главной.

function indicatorsBlock(array $items, bool $enabled = true): HomeBlock
{
    return HomeBlock::query()->updateOrCreate(
        ['type' => 'indicators'],
        [
            'title' => ['ru' => 'Ключевые показатели', 'tg' => '', 'en' => ''],
            'enabled' => $enabled,
            'sort' => 99,
            'config' => ['items' => $items],
        ],
    );
}

it('returns the indicators an editor entered', function () {
    indicatorsBlock([
        ['value' => '247', 'label' => ['ru' => 'спасательных операций с начала года', 'tg' => 'амалиёти наҷотдиҳӣ']],
        ['value' => '86 500', 'label' => ['ru' => 'граждан прошли обучение', 'tg' => 'шаҳрванд омӯзиш гузаштанд']],
    ]);

    $response = $this->getJson('/api/v1/home?locale=ru')->assertOk();

    expect($response->json('data.indicators'))->toHaveCount(2)
        // Значение остаётся строкой: редактор пишет «86 500» с разделителем
        // разрядов, и приводить это к числу значило бы терять его выбор.
        ->and($response->json('data.indicators.1.value'))->toBe('86 500')
        ->and($response->json('data.indicators.0.label'))
        ->toBe('спасательных операций с начала года');
});

it('serves each locale its own labels', function () {
    indicatorsBlock([
        ['value' => '247', 'label' => ['ru' => 'операций', 'tg' => 'амалиёт']],
    ]);

    $ru = $this->getJson('/api/v1/home?locale=ru')->assertOk();
    $tg = $this->getJson('/api/v1/home?locale=tg')->assertOk();

    expect($ru->json('data.indicators.0.label'))->toBe('операций')
        ->and($tg->json('data.indicators.0.label'))->toBe('амалиёт');
});

it('skips an indicator that has no label in the requested language', function () {
    // Число без пояснения ничего не сообщает, а русская подпись на таджикской
    // версии страницы недопустима.
    indicatorsBlock([
        ['value' => '247', 'label' => ['ru' => 'операций']],
        ['value' => '68', 'label' => ['ru' => 'подразделений', 'tg' => 'воҳид']],
    ]);

    $response = $this->getJson('/api/v1/home?locale=tg')->assertOk();

    expect($response->json('data.indicators'))->toHaveCount(1)
        ->and($response->json('data.indicators.0.value'))->toBe('68');
});

it('ignores malformed entries instead of failing', function () {
    // config пишет форма, и старые строки могут не совпадать по форме.
    indicatorsBlock([
        ['label' => ['ru' => 'без числа']],
        ['value' => '12'],
        ['value' => '   ', 'label' => ['ru' => 'пустое число']],
        ['value' => '68', 'label' => ['ru' => 'подразделений']],
    ]);

    $response = $this->getJson('/api/v1/home?locale=ru')->assertOk();

    expect($response->json('data.indicators'))->toHaveCount(1)
        ->and($response->json('data.indicators.0.value'))->toBe('68');
});

it('returns an empty list when the editor entered none', function () {
    indicatorsBlock([]);

    $response = $this->getJson('/api/v1/home?locale=ru')->assertOk();

    expect($response->json('data.indicators'))->toBe([]);
});
