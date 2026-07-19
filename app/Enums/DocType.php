<?php

namespace App\Enums;

/**
 * Official document classification.
 */
enum DocType: string
{
    case Law = 'law';
    case Resolution = 'resolution';
    case Order = 'order';
    case Report = 'report';
    case Plan = 'plan';
    case Norm = 'norm';
    case Instruction = 'instruction';
    case OpenData = 'open_data';
    case Form = 'form';

    public function label(): string
    {
        return match ($this) {
            self::Law => 'Закон',
            self::Resolution => 'Постановление',
            self::Order => 'Приказ',
            self::Report => 'Отчёт',
            self::Plan => 'План',
            self::Norm => 'Норматив',
            self::Instruction => 'Инструкция',
            self::OpenData => 'Открытые данные',
            self::Form => 'Форма',
        };
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $case): array => ['value' => $case->value, 'label' => $case->label()],
            self::cases(),
        );
    }
}
