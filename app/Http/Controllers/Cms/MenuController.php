<?php

namespace App\Http\Controllers\Cms;

use App\Http\Controllers\Controller;
use App\Http\Requests\Menu\MenuRequest;
use App\Jobs\RevalidateFrontend;
use App\Models\MenuItem;
use App\Support\FrontendRevalidation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
            $atLocation = $items->where('location', $location);
            $menus[$location] = $atLocation
                ->whereNull('parent_id')
                ->map(fn (MenuItem $item): array => $this->serializeItem(
                    $item,
                    array_values($atLocation->where('parent_id', $item->id)->all()),
                ))->values()->all();
        }

        return Inertia::render('menu/index', ['menus' => $menus]);
    }

    public function update(MenuRequest $request): RedirectResponse
    {
        abort_unless((bool) $request->user()?->can('settings.edit'), 403);

        DB::transaction(function () use ($request): void {
            foreach (self::LOCATIONS as $location) {
                /** @var array<int, array<string, mixed>> $rows */
                $rows = $request->input("items.{$location}", []);
                $this->syncLocation($location, array_values($rows));
            }
        });

        $payload = FrontendRevalidation::forShell();
        RevalidateFrontend::dispatch(
            type: $payload['type'],
            id: $payload['id'],
            slug: $payload['slug'],
            locales: $payload['locales'],
            event: $payload['event'],
        )->afterCommit();

        return back()->with('success', 'Меню сайта сохранено.');
    }

    /**
     * @param  list<MenuItem>  $children
     * @return array{id: int, label: array<string, string>, url: string|null, enabled: bool, children?: list<array{id: int, label: array<string, string>, url: string|null, enabled: bool}>}
     */
    private function serializeItem(MenuItem $item, array $children = []): array
    {
        $payload = [
            'id' => $item->id,
            'label' => $item->getTranslations('label'),
            'url' => $item->url,
            'enabled' => (bool) $item->enabled,
        ];

        if ($children !== []) {
            $payload['children'] = array_map(
                fn (MenuItem $child): array => $this->serializeItem($child),
                $children,
            );
        } else {
            $payload['children'] = [];
        }

        return $payload;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function syncLocation(string $location, array $rows): void
    {
        $keep = [];

        foreach ($rows as $sort => $row) {
            $parent = $this->persistItem($location, $row, null, $sort);

            if ($parent === null) {
                continue;
            }

            $keep[] = $parent->id;

            /** @var list<array<string, mixed>> $children */
            $children = is_array($row['children'] ?? null) ? array_values($row['children']) : [];

            foreach ($children as $childSort => $childRow) {
                $child = $this->persistItem($location, $childRow, $parent->id, $childSort);

                if ($child !== null) {
                    $keep[] = $child->id;
                }
            }
        }

        // restrictOnDelete: children first, then roots.
        MenuItem::query()
            ->where('location', $location)
            ->whereNotNull('parent_id')
            ->whereNotIn('id', $keep)
            ->delete();

        MenuItem::query()
            ->where('location', $location)
            ->whereNull('parent_id')
            ->whereNotIn('id', $keep)
            ->delete();
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function persistItem(string $location, array $row, ?int $parentId, int $sort): ?MenuItem
    {
        /** @var array<string, string> $labels */
        $labels = is_array($row['label'] ?? null)
            ? array_filter($row['label'], fn ($value): bool => is_string($value) && trim($value) !== '')
            : [];

        if (($labels['ru'] ?? '') === '') {
            return null;
        }

        $item = ! empty($row['id'])
            ? MenuItem::query()->where('location', $location)->find((int) $row['id'])
            : null;
        $item ??= new MenuItem;

        $item->location = $location;
        $item->setTranslations('label', $labels);
        $item->url = is_string($row['url'] ?? null) && $row['url'] !== '' ? $row['url'] : null;
        $item->enabled = (bool) ($row['enabled'] ?? true);
        $item->sort = $sort;
        $item->parent_id = $parentId;
        $item->save();

        return $item;
    }
}
