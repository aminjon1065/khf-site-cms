<?php

namespace App\Enums;

/**
 * Type of public announcement.
 */
enum AnnouncementKind: string
{
    case Vacancy = 'vacancy';
    case Tender = 'tender';

    public function label(): string
    {
        return match ($this) {
            self::Vacancy => 'Вакансия',
            self::Tender => 'Тендер',
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
