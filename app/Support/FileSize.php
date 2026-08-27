<?php

namespace App\Support;

/**
 * Человекочитаемый размер файла для публичного API.
 *
 * Локаль влияет и на единицы, и на десятичный разделитель: «0,8 МБ» против
 * «0.8 MB». Строка попадает прямо в интерфейс рядом с ссылкой на файл, поэтому
 * формат — часть контракта API, а не деталь отображения.
 *
 * В `MediaController` живёт свой форматтер: он служит админке, знает ГБ и
 * округляет иначе. Их намеренно не объединяли — свести к одному значило бы
 * менять то, что видит редактор, ради экономии десяти строк.
 */
final class FileSize
{
    private const MEGABYTE = 1048576;

    private const KILOBYTE = 1024;

    public static function human(int $bytes, string $locale): string
    {
        $units = $locale === 'en'
            ? ['mb' => 'MB', 'kb' => 'KB', 'b' => 'B']
            : ['mb' => 'МБ', 'kb' => 'КБ', 'b' => 'Б'];

        if ($bytes >= self::MEGABYTE) {
            return self::decimal($bytes / self::MEGABYTE, $locale).' '.$units['mb'];
        }

        if ($bytes >= self::KILOBYTE) {
            return self::decimal($bytes / self::KILOBYTE, $locale).' '.$units['kb'];
        }

        return $bytes.' '.$units['b'];
    }

    private static function decimal(float $value, string $locale): string
    {
        $rounded = (string) round($value, 1);

        return $locale === 'en' ? $rounded : str_replace('.', ',', $rounded);
    }
}
