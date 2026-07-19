<?php

namespace App\Http\Controllers\Cms;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Renders the generic "Раздел в структуре" placeholder for site sections that
 * follow the same DataTable/FilterBar/SavedViews patterns but aren't yet built.
 */
class SectionController extends Controller
{
    /**
     * @var array<string, string>
     */
    private const TITLES = [
        'notify' => 'Оповещения',
        'contacts' => 'Экстренные контакты',
        'pages' => 'Страницы',
        'announcements' => 'Объявления',
        'projects' => 'Проекты',
        'regions' => 'Региональные подразделения',
        'taxonomy' => 'Категории и теги',
        'menu' => 'Меню сайта',
    ];

    public function __invoke(string $key): Response
    {
        return Inertia::render('section', [
            'sectionKey' => $key,
            'title' => self::TITLES[$key] ?? 'Раздел',
        ]);
    }
}
