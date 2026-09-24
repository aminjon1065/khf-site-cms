<?php

namespace App\Enums;

/**
 * The three roles every installation starts with (owner decision,
 * 2026-09-24). The administrator changes the rights of the other two and
 * builds more roles on the «Роли и права» screen; the administrator's own
 * role always has every right.
 */
enum RoleName: string
{
    case Admin = 'admin';
    case ChiefEditor = 'chief_editor';
    case Editor = 'editor';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Администратор',
            self::ChiefEditor => 'Главный редактор',
            self::Editor => 'Редактор',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Admin => 'Всё, включая пользователей, роли и настройки системы',
            self::ChiefEditor => 'Все материалы сайта: публикует, согласует чужие и удаляет',
            self::Editor => 'Готовит материалы. Новости, проекты, объявления и страницы публикует сам, предупреждения, инструкции и документы — через согласование',
        };
    }
}
