<?php

namespace App\Http\Controllers\Cms;

use App\Http\Controllers\Controller;
use App\Http\Requests\HomeBlock\HomeBlockRequest;
use App\Models\HomeBlock;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HomeBlockController extends Controller
{
    /**
     * @var array<string, string>
     */
    private const TYPE_LABELS = [
        'active_alerts' => 'Оперативная сводка (предупреждения)',
        'latest_news' => 'Последние новости',
        'instructions' => 'Инструкции населению',
        'documents' => 'Официальные документы',
        'announcements' => 'Объявления',
        'projects' => 'Проекты',
        'regions_map' => 'Карта регионов',
        'emergency_contacts' => 'Экстренные контакты',
    ];

    /**
     * Block types that render a limited list (support a `limit` config).
     *
     * @var list<string>
     */
    private const WITH_LIMIT = ['active_alerts', 'latest_news', 'instructions', 'documents', 'announcements', 'projects'];

    public function index(Request $request): Response
    {
        abort_unless((bool) $request->user()?->can('home.view'), 403);

        $blocks = HomeBlock::query()->orderBy('sort')->get()->map(function (HomeBlock $block): array {
            $config = $block->config ?? [];

            return [
                'id' => $block->id,
                'type' => $block->type,
                'type_label' => self::TYPE_LABELS[$block->type] ?? $block->type,
                'title' => $block->getTranslations('title'),
                'enabled' => (bool) $block->enabled,
                'sort' => (int) $block->sort,
                'supports_limit' => in_array($block->type, self::WITH_LIMIT, true),
                'limit' => isset($config['limit']) ? (int) $config['limit'] : null,
            ];
        })->all();

        return Inertia::render('home-blocks/index', ['blocks' => $blocks]);
    }

    public function update(HomeBlockRequest $request): RedirectResponse
    {
        abort_unless((bool) $request->user()?->can('home.edit'), 403);

        /** @var array<int, array<string, mixed>> $rows */
        $rows = $request->input('blocks', []);

        foreach (array_values($rows) as $sort => $row) {
            $block = HomeBlock::query()->find((int) $row['id']);

            if ($block === null) {
                continue;
            }

            $block->enabled = (bool) ($row['enabled'] ?? false);
            $block->sort = $sort;

            /** @var array<string, string|null> $title */
            $title = is_array($row['title'] ?? null) ? $row['title'] : [];
            $block->setTranslations('title', array_filter(
                $title,
                fn (?string $v): bool => $v !== null && trim($v) !== '',
            ));

            $config = $block->config ?? [];
            $limit = $row['limit'] ?? null;

            if ($limit !== null && $limit !== '') {
                $config['limit'] = (int) $limit;
            } else {
                unset($config['limit']);
            }

            $block->config = $config;
            $block->save();
        }

        return back()->with('success', 'Главная страница обновлена.');
    }
}
