<?php

namespace App\Support;

final class UsabilityStudy
{
    /**
     * @var array<string, array{label: string, target_seconds: int|null}>
     */
    public const TASKS = [
        'news_first' => ['label' => 'Создать первую новость на двух языках', 'target_seconds' => 600],
        'media_crop' => ['label' => 'Загрузить и кадрировать обложку', 'target_seconds' => null],
        'alt_fix' => ['label' => 'Найти и исправить alt изображения', 'target_seconds' => null],
        'schedule_publish' => ['label' => 'Запланировать публикацию', 'target_seconds' => null],
        'returned_fix' => ['label' => 'Найти возвращённый материал и исправить', 'target_seconds' => null],
        'trash_restore' => ['label' => 'Восстановить удалённый материал или media', 'target_seconds' => null],
        'critical_alert' => ['label' => 'Создать критическое предупреждение', 'target_seconds' => 180],
        'news_repeat' => ['label' => 'Повторно создать новость на двух языках', 'target_seconds' => 300],
    ];

    /**
     * @return list<string>
     */
    public static function taskKeys(): array
    {
        return array_keys(self::TASKS);
    }

    /**
     * Standard SUS scoring: odd items contribute response − 1, even items
     * contribute 5 − response; the total is multiplied by 2.5.
     *
     * @param  list<int>  $responses
     */
    public static function susScore(array $responses): float
    {
        $score = 0;

        foreach ($responses as $index => $response) {
            $score += $index % 2 === 0 ? $response - 1 : 5 - $response;
        }

        return round($score * 2.5, 2);
    }
}
