<?php

namespace App\Support;

use App\Models\Alert;
use App\Models\Announcement;
use App\Models\Instruction;
use App\Models\News;
use App\Models\Page;
use App\Models\Project;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * The materials that have an address on the public site in a language: the
 * same rows the list endpoint of each type shows, with its visibility scope
 * and the language rule (PublicLocale). One definition for the slug listings
 * that pre-render pages and for sitemap.xml, so the two never disagree.
 */
class PublicAddresses
{
    /**
     * Content types with a public detail route, as the API names them.
     *
     * @var list<string>
     */
    public const TYPES = [
        'news',
        'projects',
        'announcements',
        'instructions',
        'pages',
        'alerts',
    ];

    /**
     * Each arm mirrors the visibility scope of that type's list endpoint,
     * including the one asymmetry: alerts list only while active, whereas
     * every other type lists everything public.
     *
     * @return Builder<covariant Model>
     */
    public static function query(string $type, string $locale): Builder
    {
        return match ($type) {
            'news' => PublicLocale::available(News::query()->public(), 'title', $locale),
            'projects' => PublicLocale::available(Project::query()->public(), 'title', $locale),
            'announcements' => PublicLocale::available(Announcement::query()->public(), 'title', $locale),
            'instructions' => PublicLocale::available(Instruction::query()->public(), 'name', $locale),
            'pages' => PublicLocale::available(Page::query()->public(), 'title', $locale),
            'alerts' => PublicLocale::available(Alert::query()->active(), 'title', $locale),
            // Unreachable through the constrained routes; kept so a future
            // edit to those constraints fails as a 404 rather than a 500.
            default => abort(404),
        };
    }
}
