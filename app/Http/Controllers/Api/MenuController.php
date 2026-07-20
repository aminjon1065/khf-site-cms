<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MenuItem;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;

/**
 * Public navigation menus (main + footer) for the Next.js site, resolved to the
 * requested locale. Supports one level of nesting.
 */
class MenuController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['data' => [
            'main' => $this->tree('main'),
            'footer' => $this->tree('footer'),
        ]]);
    }

    /**
     * @return list<array{label: string, url: string|null, children: list<array{label: string, url: string|null}>}>
     */
    private function tree(string $location): array
    {
        $locale = app()->getLocale();

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
