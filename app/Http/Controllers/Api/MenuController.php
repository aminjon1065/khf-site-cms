<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MenuItem;
use App\Services\PublicReadModelCache;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;

/**
 * Public navigation menus (main + footer) for the Next.js site, resolved to the
 * requested locale. Supports one level of nesting.
 */
class MenuController extends Controller
{
    public function __construct(private readonly PublicReadModelCache $cache) {}

    public function index(): JsonResponse
    {
        $locale = app()->getLocale();
        $data = $this->cache->remember(
            PublicReadModelCache::MENU,
            $locale,
            fn (): array => [
                'main' => $this->tree('main', $locale),
                'footer' => $this->tree('footer', $locale),
            ],
        );

        return response()->json(['data' => $data]);
    }

    /**
     * @return list<array{label: string, url: string|null, children: list<array{label: string, url: string|null}>}>
     */
    private function tree(string $location, string $locale): array
    {
        /** @var Collection<int, MenuItem> $items */
        $items = MenuItem::query()
            ->where('location', $location)
            ->where('enabled', true)
            ->orderBy('sort')
            ->get();

        return array_values($items->whereNull('parent_id')->map(fn (MenuItem $item): array => [
            'label' => (string) $item->getTranslation('label', $locale, true),
            'url' => $item->url,
            'children' => array_values($items->where('parent_id', $item->id)->map(fn (MenuItem $child): array => [
                'label' => (string) $child->getTranslation('label', $locale, true),
                'url' => $child->url,
            ])->all()),
        ])->all());
    }
}
