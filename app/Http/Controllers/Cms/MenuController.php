<?php

namespace App\Http\Controllers\Cms;

use App\Http\Controllers\Controller;
use App\Http\Requests\Menu\MenuRequest;
use App\Models\MenuItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MenuController extends Controller
{
    /**
     * @var list<string>
     */
    private const LOCATIONS = ['main', 'footer'];

    public function index(Request $request): Response
    {
        abort_unless((bool) $request->user()?->can('settings.view'), 403);

        $items = MenuItem::query()->orderBy('sort')->get();

        $menus = [];
        foreach (self::LOCATIONS as $location) {
            $menus[$location] = $items
                ->where('location', $location)
                ->whereNull('parent_id')
                ->map(fn (MenuItem $m): array => [
                    'id' => $m->id,
                    'label' => $m->getTranslations('label'),
                    'url' => $m->url,
                    'enabled' => (bool) $m->enabled,
                ])->values()->all();
        }

        return Inertia::render('menu/index', ['menus' => $menus]);
    }

    public function update(MenuRequest $request): RedirectResponse
    {
        abort_unless((bool) $request->user()?->can('settings.edit'), 403);

        foreach (self::LOCATIONS as $location) {
            /** @var array<int, array<string, mixed>> $rows */
            $rows = $request->input("items.{$location}", []);
            $keep = [];

            foreach (array_values($rows) as $sort => $row) {
                /** @var array<string, string|null> $labels */
                $labels = is_array($row['label'] ?? null)
                    ? array_filter($row['label'], fn ($v): bool => is_string($v) && trim($v) !== '')
                    : [];

                if (($labels['ru'] ?? '') === '') {
                    continue; // drop rows without a Russian label
                }

                $item = ! empty($row['id']) ? MenuItem::query()->find((int) $row['id']) : null;
                $item ??= new MenuItem;

                $item->location = $location;
                $item->setTranslations('label', $labels);
                $item->url = is_string($row['url'] ?? null) ? $row['url'] : null;
                $item->enabled = (bool) ($row['enabled'] ?? true);
                $item->sort = $sort;
                $item->parent_id = null;
                $item->save();

                $keep[] = $item->id;
            }

            MenuItem::query()->where('location', $location)->whereNotIn('id', $keep)->delete();
        }

        return back()->with('success', 'Меню сайта сохранено.');
    }
}
