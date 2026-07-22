<?php

namespace App\Support;

final class PublicApiLabels
{
    /** @var array<string, array<string, array<string, string>>> */
    private const LABELS = [
        'alert_level' => [
            'info' => ['tg' => 'Сатҳи иттилоотӣ', 'ru' => 'Информационный уровень', 'en' => 'Information level'],
            'warning' => ['tg' => 'Сатҳи норанҷӣ', 'ru' => 'Оранжевый уровень', 'en' => 'Orange level'],
            'danger' => ['tg' => 'Сатҳи сурх', 'ru' => 'Красный уровень', 'en' => 'Red level'],
            'critical' => ['tg' => 'Сатҳи сурх', 'ru' => 'Красный уровень', 'en' => 'Red level'],
        ],
        'alert_status' => [
            'active' => ['tg' => 'Амал мекунад', 'ru' => 'Действует', 'en' => 'Active'],
            'completed' => ['tg' => 'Анҷом ёфт', 'ru' => 'Завершено', 'en' => 'Ended'],
        ],
        'hazard' => [
            'mudflow' => ['tg' => 'Сел', 'ru' => 'Сель', 'en' => 'Mudflow'],
            'earthquake' => ['tg' => 'Заминларза', 'ru' => 'Землетрясение', 'en' => 'Earthquake'],
            'flood' => ['tg' => 'Обхезӣ', 'ru' => 'Наводнение', 'en' => 'Flood'],
            'avalanche' => ['tg' => 'Тарма', 'ru' => 'Лавина', 'en' => 'Avalanche'],
            'fire' => ['tg' => 'Сӯхтор', 'ru' => 'Пожар', 'en' => 'Fire'],
            'wind' => ['tg' => 'Шамоли сахт', 'ru' => 'Сильный ветер', 'en' => 'Strong wind'],
            'heat' => ['tg' => 'Гармои шадид', 'ru' => 'Жара', 'en' => 'Extreme heat'],
            'frost' => ['tg' => 'Сармо', 'ru' => 'Мороз', 'en' => 'Frost'],
            'landslide' => ['tg' => 'Лағжиши замин', 'ru' => 'Оползень', 'en' => 'Landslide'],
            'storm' => ['tg' => 'Раъду барқ', 'ru' => 'Гроза', 'en' => 'Thunderstorm'],
        ],
        'territory' => [
            'country' => ['tg' => 'Тамоми Ҷумҳурии Тоҷикистон', 'ru' => 'Вся Республика Таджикистан', 'en' => 'Entire Republic of Tajikistan'],
        ],
        'announcement_kind' => [
            'vacancy' => ['tg' => 'Ҷойи корӣ', 'ru' => 'Вакансия', 'en' => 'Vacancy'],
            'tender' => ['tg' => 'Тендер', 'ru' => 'Тендер', 'en' => 'Tender'],
        ],
        'deadline' => [
            'unlimited' => ['tg' => 'Бе муҳлат', 'ru' => 'бессрочно', 'en' => 'No deadline'],
            'until' => ['tg' => 'то', 'ru' => 'до', 'en' => 'until'],
            'closed' => ['tg' => 'анҷом ёфт', 'ru' => 'завершён', 'en' => 'closed'],
        ],
        'document_type' => [
            'law' => ['tg' => 'Қонун', 'ru' => 'Закон', 'en' => 'Law'],
            'resolution' => ['tg' => 'Қарор', 'ru' => 'Постановление', 'en' => 'Resolution'],
            'order' => ['tg' => 'Фармон', 'ru' => 'Приказ', 'en' => 'Order'],
            'report' => ['tg' => 'Ҳисобот', 'ru' => 'Отчёт', 'en' => 'Report'],
            'plan' => ['tg' => 'Нақша', 'ru' => 'План', 'en' => 'Plan'],
            'norm' => ['tg' => 'Меъёр', 'ru' => 'Норматив', 'en' => 'Regulation'],
            'instruction' => ['tg' => 'Дастурамал', 'ru' => 'Инструкция', 'en' => 'Instruction'],
            'open_data' => ['tg' => 'Маълумоти кушода', 'ru' => 'Открытые данные', 'en' => 'Open data'],
            'form' => ['tg' => 'Шакл', 'ru' => 'Форма', 'en' => 'Form'],
        ],
        'project_status' => [
            'preparation' => ['tg' => 'Омодасозӣ', 'ru' => 'Подготовка', 'en' => 'Preparation'],
            'implementing' => ['tg' => 'Амалӣ мешавад', 'ru' => 'Реализуется', 'en' => 'In progress'],
            'completed' => ['tg' => 'Анҷом ёфт', 'ru' => 'Завершён', 'en' => 'Completed'],
        ],
        'region_type' => [
            'city' => ['tg' => 'Шаҳри аҳамияти ҷумҳуриявӣ', 'ru' => 'Город республиканского значения', 'en' => 'City of republican significance'],
            'oblast' => ['tg' => 'Вилоят', 'ru' => 'Область', 'en' => 'Region'],
            'gbao' => ['tg' => 'Вилояти мухтор', 'ru' => 'Автономная область', 'en' => 'Autonomous region'],
            'rrp' => ['tg' => 'Ноҳияҳои тобеи ҷумҳурӣ', 'ru' => 'Районы республиканского подчинения', 'en' => 'Districts of republican subordination'],
        ],
        'region_status' => [
            'none' => ['tg' => 'Вазъият муқаррарӣ', 'ru' => 'Обстановка штатная', 'en' => 'Normal conditions'],
            'info' => ['tg' => 'Огоҳии иттилоотӣ', 'ru' => 'Информационное уведомление', 'en' => 'Information notice'],
            'warning' => ['tg' => 'Огоҳӣ амал мекунад', 'ru' => 'Действует предупреждение', 'en' => 'Warning active'],
            'danger' => ['tg' => 'Вазъияти хатарнок', 'ru' => 'Опасная обстановка', 'en' => 'Dangerous conditions'],
            'critical' => ['tg' => 'Вазъияти фавқулода', 'ru' => 'Критическая ситуация', 'en' => 'Critical situation'],
        ],
        'alert_meta' => [
            'published' => ['tg' => 'Нашр шуд', 'ru' => 'Опубликовано', 'en' => 'Published'],
            'active_until' => ['tg' => 'Амал мекунад то', 'ru' => 'Действует до', 'en' => 'Active until'],
            'ended_at' => ['tg' => 'Амал мекард то', 'ru' => 'Действовало до', 'en' => 'Ended at'],
            'regions' => ['tg' => 'Минтақаҳо', 'ru' => 'Регионы', 'en' => 'Regions'],
            'source' => ['tg' => 'Манбаъ', 'ru' => 'Источник', 'en' => 'Source'],
        ],
    ];

    public static function get(string $group, string $code, string $locale): string
    {
        $labels = self::LABELS[$group][$code] ?? [];

        return $labels[$locale] ?? $labels['ru'] ?? $code;
    }
}
