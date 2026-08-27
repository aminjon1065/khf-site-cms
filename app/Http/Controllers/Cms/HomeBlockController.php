<?php

namespace App\Http\Controllers\Cms;

use App\Http\Controllers\Controller;
use App\Http\Requests\HomeBlock\HomeBlockRequest;
use App\Models\HomeBlock;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
        'indicators' => 'Ключевые показатели',
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
                'supports_items' => $block->type === 'indicators',
                'items' => is_array($config['items'] ?? null) ? $config['items'] : [],
            ];
        })->all();

        return Inertia::render('home-blocks/index', ['blocks' => $blocks]);
    }

    public function update(HomeBlockRequest $request): RedirectResponse
    {
        abort_unless((bool) $request->user()?->can('home.edit'), 403);

        /** @var array<int, array<string, mixed>> $rows */
        $rows = $request->input('blocks', []);

        DB::transaction(function () use ($rows): void {
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

                if ($block->type === 'indicators') {
                    $config['items'] = $this->cleanIndicators($row['items'] ?? null);
                }

                $block->config = $config;
                $block->save();
            }
        });

        return back()->with('success', 'Главная страница обновлена.');
    }

    /**
     * Показатели ведомства: число и подпись к нему на трёх языках.
     *
     * Считать их CMS не может — «спасательных операций» и «человек спасено»
     * нет ни в одной таблице, эти цифры приходят из отчётности. Поэтому их
     * вводит редактор, а не вычисляет система.
     *
     * Запись без числа или без подписи хотя бы на одном языке отбрасывается:
     * пустая плитка на главной хуже отсутствующей.
     *
     * @return list<array{value: string, label: array<string, string>}>
     */
    private function cleanIndicators(mixed $raw): array
    {
        $items = [];

        foreach (is_array($raw) ? $raw : [] as $row) {
            if (! is_array($row)) {
                continue;
            }

            $value = is_string($row['value'] ?? null) ? trim($row['value']) : '';
            $labels = is_array($row['label'] ?? null) ? $row['label'] : [];
            $label = [];

            foreach ($labels as $locale => $text) {
                if (is_string($locale) && is_string($text) && trim($text) !== '') {
                    $label[$locale] = trim($text);
                }
            }

            if ($value === '' || $label === []) {
                continue;
            }

            $items[] = ['value' => $value, 'label' => $label];
        }

        return $items;
    }
}
