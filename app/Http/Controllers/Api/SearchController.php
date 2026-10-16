<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Alert;
use App\Models\Announcement;
use App\Models\Document;
use App\Models\Instruction;
use App\Models\News;
use App\Models\Page;
use App\Models\Project;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use stdClass;

class SearchController extends Controller
{
    /**
     * Search public content in SQL and paginate the combined result set in the
     * database. Only fields of the requested locale participate in matching;
     * Russian is used solely as an explicitly detectable display fallback.
     *
     * @throws ValidationException
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['required', 'string', 'min:2', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1'],
        ]);

        $term = trim($validated['q']);
        $locale = app()->getLocale();
        $page = max((int) ($validated['page'] ?? 1), 1);
        $perPage = min(max((int) ($validated['per_page'] ?? 20), 1), 50);

        $queries = [
            $this->news($term, $locale),
            $this->alerts($term, $locale),
            $this->instructions($term, $locale),
            $this->documents($term, $locale),
            $this->projects($term, $locale),
            $this->announcements($term, $locale),
            $this->pages($term, $locale),
        ];

        $union = array_shift($queries);
        foreach ($queries as $query) {
            $union->unionAll($query);
        }

        $results = DB::query()
            ->fromSub($union, 'search_results')
            ->orderByRaw(
                "COALESCE(NULLIF(requested_title, ''), NULLIF(russian_title, ''), '') LIKE ? ESCAPE '!' DESC",
                [self::containsPattern($term)],
            )
            ->orderByRaw('published_at IS NULL')
            ->orderByDesc('published_at')
            ->paginate($perPage, ['*'], 'page', $page);

        $items = collect($results->items())->map(fn (stdClass $row): array => [
            'type' => (string) $row->type,
            'title' => (string) ($row->requested_title ?: $row->russian_title),
            'excerpt' => Str::limit(trim(strip_tags((string) ($row->requested_excerpt ?: $row->russian_excerpt))), 220),
            'path' => $this->path((string) $row->type, $row->resource_key),
            'published_at' => $row->published_at !== null ? (string) $row->published_at : null,
        ])->all();

        return response()->json([
            'data' => $items,
            'meta' => [
                'current_page' => $results->currentPage(),
                'last_page' => $results->lastPage(),
                'per_page' => $results->perPage(),
                'total' => $results->total(),
            ],
            'links' => [
                'prev' => $results->previousPageUrl(),
                'next' => $results->nextPageUrl(),
            ],
        ]);
    }

    private function news(string $term, string $locale): QueryBuilder
    {
        $query = News::query()->public();
        $this->whereMatches($query, $term, $locale, ['title', 'summary', 'body']);

        return $this->shape($query, 'news', 'title', 'summary', 'slug', $locale);
    }

    private function alerts(string $term, string $locale): QueryBuilder
    {
        $query = Alert::query()->public();
        $this->whereMatches($query, $term, $locale, ['title', 'summary', 'body']);

        return $this->shape($query, 'alert', 'title', 'summary', 'slug', $locale);
    }

    private function instructions(string $term, string $locale): QueryBuilder
    {
        $query = Instruction::query()->public();
        $this->whereMatches($query, $term, $locale, ['name', 'summary', 'body']);

        return $this->shape($query, 'instruction', 'name', 'summary', 'slug', $locale);
    }

    private function documents(string $term, string $locale): QueryBuilder
    {
        $query = Document::query()->public();
        $this->whereMatches($query, $term, $locale, ['name'], ['number']);

        return $this->shape($query, 'document', 'name', null, null, $locale, 'number');
    }

    private function projects(string $term, string $locale): QueryBuilder
    {
        $query = Project::query()->public();
        $this->whereMatches($query, $term, $locale, ['title', 'summary', 'body']);

        return $this->shape($query, 'project', 'title', 'summary', 'slug', $locale);
    }

    private function announcements(string $term, string $locale): QueryBuilder
    {
        $query = Announcement::query()->public();
        $this->whereMatches($query, $term, $locale, ['title', 'body'], ['org']);

        return $this->shape($query, 'announcement', 'title', 'body', 'slug', $locale);
    }

    private function pages(string $term, string $locale): QueryBuilder
    {
        $query = Page::query()->public();
        $this->whereMatches($query, $term, $locale, ['title', 'body']);

        return $this->shape($query, 'page', 'title', 'body', 'slug', $locale);
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @param  list<string>  $translatedFields
     * @param  list<string>  $plainFields
     */
    private function whereMatches(
        Builder $query,
        string $term,
        string $locale,
        array $translatedFields,
        array $plainFields = [],
    ): void {
        $variants = array_values(array_unique([
            $term,
            Str::lower($term),
            Str::upper($term),
            Str::ucfirst(Str::lower($term)),
        ]));

        $query->where(function (Builder $where) use ($variants, $locale, $translatedFields, $plainFields): void {
            foreach ($variants as $variant) {
                $pattern = self::containsPattern($variant);
                foreach ($translatedFields as $field) {
                    $this->orWhereContains($where, $field, $locale, $pattern);
                }
                foreach ($plainFields as $field) {
                    $this->orWhereContains($where, $field, null, $pattern);
                }
            }
        });
    }

    /**
     * `column LIKE ? ESCAPE '!'` for one searched column. Laravel's 'like'
     * has no ESCAPE, and raw SQL is built from literals only: the column is
     * one of the searched names, the language goes in as a JSON-path binding.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $where
     */
    private function orWhereContains(Builder $where, string $field, ?string $locale, string $pattern): void
    {
        $column = match ($field) {
            'title' => 'title',
            'summary' => 'summary',
            'body' => 'body',
            'name' => 'name',
            'number' => 'number',
            'org' => 'org',
            default => throw new InvalidArgumentException("Column [{$field}] is not searchable."),
        };

        if ($locale === null) {
            $where->orWhereRaw("{$column} like ? escape '!'", [$pattern]);

            return;
        }

        // The same JSON value Laravel reads for `title->ru` on each driver.
        $where->orWhereRaw(
            $where->getModel()->getConnection()->getDriverName() === 'mysql'
                ? "json_unquote(json_extract({$column}, ?)) like ? escape '!'"
                : "json_extract({$column}, ?) like ? escape '!'",
            ['$."'.$locale.'"', $pattern],
        );
    }

    /**
     * «Contains» as a LIKE pattern with the searched text taken literally:
     * `%` and `_` typed by a citizen are characters, not wildcards (a query
     * of `%%` used to match every public record of all seven types).
     */
    private static function containsPattern(string $term): string
    {
        return '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $term).'%';
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     */
    private function shape(
        Builder $query,
        string $type,
        string $titleField,
        ?string $excerptField,
        ?string $keyField,
        string $locale,
        ?string $plainExcerptField = null,
    ): QueryBuilder {
        $query->selectRaw('? as type', [$type])
            ->addSelect(
                "{$titleField}->{$locale} as requested_title",
                "{$titleField}->ru as russian_title",
            );

        if ($excerptField !== null) {
            $query->addSelect(
                "{$excerptField}->{$locale} as requested_excerpt",
                "{$excerptField}->ru as russian_excerpt",
            );
        } elseif ($plainExcerptField !== null) {
            $query->addSelect(
                "{$plainExcerptField} as requested_excerpt",
                "{$plainExcerptField} as russian_excerpt",
            );
        } else {
            $query->selectRaw("'' as requested_excerpt")
                ->selectRaw("'' as russian_excerpt");
        }

        if ($keyField !== null) {
            $query->addSelect("{$keyField} as resource_key");
        } else {
            $query->selectRaw('NULL as resource_key');
        }

        return $query->addSelect('published_at')->toBase();
    }

    private function path(string $type, mixed $key): string
    {
        $slug = is_string($key) ? $key : '';

        return match ($type) {
            'news' => "/news/{$slug}",
            'alert' => "/alerts/{$slug}",
            'instruction' => "/guides/{$slug}",
            'project' => "/projects/{$slug}",
            'page' => match ($slug) {
                'about' => '/about',
                'leadership' => '/leadership',
                'structure' => '/structure',
                'symbols' => '/symbols',
                'sos' => '/sos',
                default => "/pages/{$slug}",
            },
            'document' => '/documents',
            'announcement' => "/announcements/{$slug}",
            default => '/',
        };
    }
}
