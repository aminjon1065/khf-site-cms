<?php

namespace App\Support;

use App\Contracts\Workflowable;
use Illuminate\Database\Eloquent\Model;

/**
 * Where a CMS record lives on the public site (khf-site-front routes).
 *
 * The site's base URL comes from configuration, so "view on site" links from
 * a staging CMS open the staging site, not production. Paths mirror the
 * front's App Router: `/{locale}/news/{slug}`, `/{locale}/guides/{slug}`, …;
 * content locale `tg` is published under the `tj` URL segment.
 */
final class PublicSite
{
    /**
     * Pages whose slug is bound to a dedicated site section: the section
     * renders the page's title, text and SEO, and `/pages/{slug}` redirects
     * there.
     *
     * @var array<string, string>
     */
    public const LINKED_PAGES = [
        'about' => '/about',
        'leadership' => '/leadership',
        'structure' => '/structure',
        'symbols' => '/symbols',
    ];

    /**
     * Pages the site links to by slug from its footer. Renaming their slug
     * would break those links, so they are protected like linked pages.
     *
     * @var list<string>
     */
    public const FOOTER_PAGES = ['privacy', 'accessibility'];

    /**
     * Detail page path prefix per content type.
     *
     * @var array<string, string>
     */
    private const DETAIL_PATHS = [
        'alert' => '/alerts',
        'announcement' => '/announcements',
        'instruction' => '/guides',
        'news' => '/news',
        'project' => '/projects',
    ];

    /**
     * Section (listing) path per content type.
     *
     * @var array<string, string>
     */
    private const SECTION_PATHS = [
        'alert' => '/alerts',
        'announcement' => '/announcements',
        'document' => '/documents',
        'instruction' => '/guides',
        'news' => '/news',
        'project' => '/projects',
    ];

    public static function baseUrl(): string
    {
        return rtrim((string) config('services.frontend.url', 'https://khf.tj'), '/');
    }

    /**
     * URL segment of a content locale (`tg` is served under `/tj`).
     */
    public static function localeSegment(string $contentLocale): string
    {
        return $contentLocale === 'tg' ? 'tj' : $contentLocale;
    }

    /**
     * A page whose slug the site depends on: it must keep its slug and can't
     * be deleted.
     */
    public static function isSystemPage(?string $slug): bool
    {
        return $slug !== null
            && (array_key_exists($slug, self::LINKED_PAGES) || in_array($slug, self::FOOTER_PAGES, true));
    }

    public static function pagePath(string $slug): string
    {
        return self::LINKED_PAGES[$slug] ?? "/pages/{$slug}";
    }

    /**
     * Locale-less public path of a record's own page, or null when the type
     * has no detail page on the site (documents) or the record has no slug.
     */
    public static function pathFor(string $type, ?string $slug): ?string
    {
        if ($slug === null || $slug === '') {
            return null;
        }

        if ($type === 'page') {
            return self::pagePath($slug);
        }

        $prefix = self::DETAIL_PATHS[$type] ?? null;

        return $prefix !== null ? "{$prefix}/{$slug}" : null;
    }

    public static function sectionPath(string $type): ?string
    {
        return self::SECTION_PATHS[$type] ?? null;
    }

    /**
     * Absolute URL of a locale-less site path.
     */
    public static function url(string $path = '/', string $contentLocale = 'ru'): string
    {
        $path = '/'.ltrim($path, '/');

        return self::baseUrl().'/'.self::localeSegment($contentLocale).($path === '/' ? '' : $path);
    }

    /**
     * Public URL of a record, or null while it isn't visible on the site in
     * that language (not published, or that language version lacks its title
     * or text — PublicLocale). Without a locale, the first language the record
     * appears in is used.
     */
    public static function urlFor(Model&Workflowable $subject, ?string $contentLocale = null): ?string
    {
        if (! $subject->getWorkflowStatus()->isPublic()) {
            return null;
        }

        $contentLocale ??= PublicLocale::firstPublishedLocale($subject) ?? 'ru';

        $type = ContentTypes::slugFor($subject);
        $slug = $subject->getAttribute('slug');
        $path = $type !== null ? self::pathFor($type, is_string($slug) ? $slug : null) : null;

        if ($path === null || ! PublicLocale::isPublishedIn($subject, $contentLocale)) {
            return null;
        }

        return self::url($path, $contentLocale);
    }
}
