<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PublicReadModelCache;
use App\Support\ContentLocales;
use App\Support\PublicAddresses;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;

/**
 * Everything sitemap.xml of the public site lists, in one response: each
 * material's address, the languages it is published in and when it last
 * changed. The sitemap used to ask /slugs eighteen times (six types × three
 * languages) and knew no dates, so search engines couldn't tell a fresh edit
 * from a page nobody touched in a year.
 *
 * «Changed» is the last real edit of the text (content_updated_at), else the
 * publication: the same moment the page itself states. A save without edits,
 * a status change or a view doesn't move it — search engines trust lastmod
 * only while it stays accurate.
 */
class SitemapController extends Controller
{
    public function __construct(private readonly PublicReadModelCache $cache) {}

    public function __invoke(): JsonResponse
    {
        $payload = $this->cache->remember(
            PublicReadModelCache::SLUGS,
            'sitemap',
            function (): array {
                $entries = [];

                foreach (PublicAddresses::TYPES as $type) {
                    array_push($entries, ...$this->entries($type));
                }

                return [
                    'data' => $entries,
                    'meta' => ['total' => count($entries)],
                ];
            },
        );

        return response()->json($payload);
    }

    /**
     * The type's materials by slug, each with the languages whose version of
     * the site shows it. Sorted by slug so an unchanged catalogue yields a
     * byte-identical payload.
     *
     * @return list<array{type: string, slug: string, locales: list<string>, modified_at: string|null}>
     */
    private function entries(string $type): array
    {
        $bySlug = [];

        foreach (ContentLocales::ALL as $locale) {
            $rows = PublicAddresses::query($type, $locale)
                ->get(['slug', 'published_at', 'content_updated_at']);

            foreach ($rows as $row) {
                $slug = $row->getAttribute('slug');

                // `slug` is nullable on alerts; an empty segment has no page.
                if (! is_string($slug) || $slug === '') {
                    continue;
                }

                $bySlug[$slug] ??= [
                    'type' => $type,
                    'slug' => $slug,
                    'locales' => [],
                    'modified_at' => $this->modifiedAt($row),
                ];
                $bySlug[$slug]['locales'][] = $locale;
            }
        }

        ksort($bySlug, SORT_STRING);

        return array_values($bySlug);
    }

    private function modifiedAt(Model $row): ?string
    {
        $date = $row->getAttribute('content_updated_at') ?? $row->getAttribute('published_at');

        return $date instanceof CarbonInterface ? $date->toIso8601String() : null;
    }
}
