<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PublicReadModelCache;
use App\Support\PublicAddresses;
use Illuminate\Http\JsonResponse;

/**
 * Slug-only listings for consumers that need nothing but the URL segment:
 * the Next.js `generateStaticParams` of every detail route and the check
 * whether a language version is published (sitemap.xml reads /sitemap).
 *
 * Those consumers used to page through the regular list endpoints and throw
 * away the whole DTO, paying for body/media/labels of every record to read one
 * field. This endpoint returns the same *rows* as the matching list endpoint —
 * same visibility scope, same locale contract — and a single column.
 */
class SlugController extends Controller
{
    /**
     * Content types with a public detail route. The route constrains `{type}`
     * to these values, so an unknown one is a 404 from the router.
     *
     * @var list<string>
     */
    public const TYPES = PublicAddresses::TYPES;

    public function __construct(private readonly PublicReadModelCache $cache) {}

    public function __invoke(string $type): JsonResponse
    {
        $locale = app()->getLocale();

        $payload = $this->cache->remember(
            PublicReadModelCache::SLUGS,
            "{$locale}:{$type}",
            function () use ($type, $locale): array {
                $slugs = $this->slugs($type, $locale);

                return [
                    'data' => $slugs,
                    'meta' => ['total' => count($slugs)],
                ];
            },
        );

        return response()->json($payload);
    }

    /**
     * @return list<string>
     */
    private function slugs(string $type, string $locale): array
    {
        // The rows of the type's list endpoint (PublicAddresses): a pure
        // payload optimisation, with no drift in which pages get pre-rendered
        // or land in the sitemap.
        $query = PublicAddresses::query($type, $locale);

        // The list endpoints order for display; slug consumers ignore order
        // entirely. Sorting by slug keeps the cached payload byte-stable, so an
        // unchanged catalogue does not churn the frontend's ISR output.
        // `slug` is nullable on alerts, and an empty segment would generate a
        // route that cannot resolve — drop those rather than emit them.
        return array_values(array_filter(array_map(
            fn (mixed $slug): string => is_string($slug) ? $slug : '',
            $query->orderBy('slug')->pluck('slug')->all(),
        )));
    }
}
