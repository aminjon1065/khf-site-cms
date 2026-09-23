<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;

final class FrontendRevalidation
{
    /** @var list<string> */
    public const LOCALES = ['ru', 'tj', 'en'];

    /**
     * @var array<string, string>
     */
    private const RESOURCES = [
        'alert' => 'alerts',
        'announcement' => 'announcements',
        'document' => 'documents',
        'instruction' => 'guides',
        'news' => 'news',
        'page' => 'pages',
        'project' => 'projects',
    ];

    /**
     * Reference data the site caches under a single tag per locale: home
     * page blocks, the leadership and structure rosters, regions and news
     * categories. Contract with khf-site-front/lib/cache-tags.ts.
     *
     * @var array<string, string>
     */
    private const REFERENCE_RESOURCES = [
        'home' => 'home',
        'leadership' => 'leadership',
        'structure' => 'structure',
        'region' => 'regions',
        'category' => 'categories',
    ];

    /** @var list<string> */
    private const HOME_TYPES = [
        'alert',
        'announcement',
        'document',
        'instruction',
        'news',
        'project',
    ];

    /** @var list<string> */
    private const SITEMAP_TYPES = [
        'alert',
        'announcement',
        'instruction',
        'news',
        'page',
        'project',
    ];

    /**
     * @return array{
     *     type: string,
     *     id: int|null,
     *     slug: string|null,
     *     locales: list<string>,
     *     event: string,
     *     tags: list<string>
     * }|null
     */
    public static function forContent(Model $subject, string $event): ?array
    {
        $type = ContentTypes::slugFor($subject);

        if ($type === null) {
            return null;
        }

        $slug = $subject->getAttribute('slug');
        $slug = is_string($slug) && $slug !== '' ? $slug : null;

        return self::payload(
            type: $type,
            id: (int) $subject->getKey(),
            slug: $slug,
            locales: self::LOCALES,
            event: $event,
        );
    }

    /**
     * @return array{
     *     type: string,
     *     id: null,
     *     slug: null,
     *     locales: list<string>,
     *     event: string,
     *     tags: list<string>
     * }
     */
    public static function forShell(string $event = 'updated'): array
    {
        return [
            'type' => 'shell',
            'id' => null,
            'slug' => null,
            'locales' => self::LOCALES,
            'event' => $event,
            'tags' => self::tags('shell', null, self::LOCALES),
        ];
    }

    /**
     * Payload for a change of reference data (see REFERENCE_RESOURCES).
     *
     * @return array{
     *     type: string,
     *     id: int|null,
     *     slug: string|null,
     *     locales: list<string>,
     *     event: string,
     *     tags: list<string>
     * }
     */
    public static function forReference(string $type, string $event = 'updated'): array
    {
        return self::payload(type: $type, id: null, slug: null, locales: self::LOCALES, event: $event);
    }

    /**
     * @param  list<string>  $locales
     * @return array{
     *     type: string,
     *     id: int|null,
     *     slug: string|null,
     *     locales: list<string>,
     *     event: string,
     *     tags: list<string>
     * }
     */
    public static function payload(
        string $type,
        ?int $id,
        ?string $slug,
        array $locales,
        string $event,
    ): array {
        $normalizedLocales = array_values(array_unique(array_intersect(self::LOCALES, $locales)));

        return [
            'type' => $type,
            'id' => $id,
            'slug' => $slug,
            'locales' => $normalizedLocales,
            'event' => $event,
            'tags' => self::tags($type, $slug, $normalizedLocales),
        ];
    }

    /**
     * @param  list<string>  $locales
     * @return list<string>
     */
    public static function tags(string $type, ?string $slug, array $locales): array
    {
        if ($type === 'shell') {
            return array_map(
                fn (string $locale): string => "cms:shell:{$locale}",
                $locales,
            );
        }

        if (isset(self::REFERENCE_RESOURCES[$type])) {
            $reference = self::REFERENCE_RESOURCES[$type];

            return array_map(
                fn (string $locale): string => "cms:{$reference}:{$locale}",
                $locales,
            );
        }

        $resource = self::RESOURCES[$type] ?? null;

        if ($resource === null) {
            return [];
        }

        $tags = [];

        foreach ($locales as $locale) {
            $tags[] = "cms:{$resource}:{$locale}";

            if ($slug !== null) {
                $tags[] = "cms:{$resource}:{$slug}:{$locale}";
            }

            if (in_array($type, self::HOME_TYPES, true)) {
                $tags[] = "cms:home:{$locale}";
            }
        }

        if (in_array($type, self::SITEMAP_TYPES, true)) {
            $tags[] = 'cms:sitemap';
        }

        return array_values(array_unique($tags));
    }
}
