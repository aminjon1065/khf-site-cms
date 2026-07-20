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
    case Projects = 'projects';
    case Announcements = 'announcements';
    case Pages = 'pages';
    case Media = 'media';
    case Taxonomy = 'taxonomy';
    case Home = 'home';
    case Regions = 'regions';
    case Submissions = 'submissions';
    case Users = 'users';
    case Settings = 'settings';

    public function label(): string
    {
        return match ($this) {
            self::Alerts => 'Предупреждения',
            self::News => 'Новости и заявления',
            self::Instructions => 'Инструкции населению',
            self::Documents => 'Документы',
            self::Projects => 'Проекты и программы',
            self::Announcements => 'Объявления',
            self::Pages => 'Страницы сайта',
            self::Media => 'Медиабиблиотека',
            self::Taxonomy => 'Категории и теги',
            self::Home => 'Главная страница',
            self::Regions => 'Регионы и районы',
            self::Submissions => 'Обращения граждан',
            self::Users => 'Пользователи и роли',
            self::Settings => 'Настройки системы',
        };
    }
}
