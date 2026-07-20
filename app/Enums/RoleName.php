<?php

namespace App\Enums;

/**
 * The nine system roles.
 */
enum RoleName: string
{
    case Superadmin = 'superadmin';
    case Admin = 'admin';
    case ChiefEditor = 'chief_editor';
    case Editor = 'editor';
    case AlertOperator = 'alert_operator';
    case Translator = 'translator';
    case RegionalEditor = 'regional_editor';
    case Approver = 'approver';
    case Viewer = 'viewer';

    public function label(): string
    {
        return match ($this) {
            self::Superadmin => 'Суперадминистратор',
            self::Admin => 'Администратор',
            self::ChiefEditor => 'Главный редактор',
            self::Editor => 'Редактор',
            self::AlertOperator => 'Оператор предупреждений',
            self::Translator => 'Переводчик',
            self::RegionalEditor => 'Региональный редактор',
            self::Approver => 'Согласующий',
            self::Viewer => 'Наблюдатель',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Superadmin => 'Полный доступ ко всем разделам и системным настройкам',
            self::Admin => 'Управление контентом, пользователями и настройками',
            self::ChiefEditor => 'Полный редакционный цикл без системных настроек',
            self::Editor => 'Создание и редактирование материалов',
            self::AlertOperator => 'Оперативная публикация предупреждений, включая критические',
            self::Translator => 'Перевод материалов на три языка',
            self::RegionalEditor => 'Материалы в пределах закреплённого региона',
            self::Approver => 'Согласование и публикация подготовленных материалов',
            self::Viewer => 'Только просмотр без права редактирования',
        };
    }

    /**
     * Whether the role is limited to its own region (enforced by policy + scope).
     */
    public function isRegionScoped(): bool
    {
        return $this === self::RegionalEditor;
    }

    /**
     * @return list<array{value: string, label: string, description: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $case): array => [
                'value' => $case->value,
                'label' => $case->label(),
                'description' => $case->description(),
            ],
            self::cases(),
        );
    }
}
