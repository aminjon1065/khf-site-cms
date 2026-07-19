<?php

namespace App\Enums;

/**
 * Content/administration modules that permissions are grouped by.
 */
enum Module: string
{
    case Alerts = 'alerts';
    case News = 'news';
    case Instructions = 'instructions';
    case Documents = 'documents';
    case Media = 'media';
    case Home = 'home';
    case Users = 'users';
    case Settings = 'settings';

    public function label(): string
    {
        return match ($this) {
            self::Alerts => 'Предупреждения',
            self::News => 'Новости и заявления',
            self::Instructions => 'Инструкции населению',
            self::Documents => 'Документы',
            self::Media => 'Медиабиблиотека',
            self::Home => 'Главная страница',
            self::Users => 'Пользователи и роли',
            self::Settings => 'Настройки системы',
        };
    }
}
