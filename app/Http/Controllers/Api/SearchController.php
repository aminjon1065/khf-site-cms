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
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
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
            ->orderByDesc('relevance')
            ->orderByRaw('published_at IS NULL')
            ->orderByDesc('published_at')
            ->paginate($perPage, ['*'], 'page', $page);

        $items = collect($results->items())->map(fn (stdClass $row): array => [
            'type' => (string) $row->type,
            'title' => (string) $row->title,
            'excerpt' => Str::limit(trim(strip_tags((string) $row->excerpt)), 220),
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

        return $this->shape($query, 'news', 'title', 'summary', 'slug', $term, $locale);
    }

    private function alerts(string $term, string $locale): QueryBuilder
    {
        $query = Alert::query()->public();
        $this->whereMatches($query, $term, $locale, ['title', 'summary', 'body']);

        return $this->shape($query, 'alert', 'title', 'summary', 'slug', $term, $locale);
    }

    private function instructions(string $term, string $locale): QueryBuilder
    {
        $query = Instruction::query()->public();
        $this->whereMatches($query, $term, $locale, ['name', 'summary', 'body']);

        return $this->shape($query, 'instruction', 'name', 'summary', 'slug', $term, $locale);
    }

    private function documents(string $term, string $locale): QueryBuilder
    {
        $query = Document::query()->public();
        $this->whereMatches($query, $term, $locale, ['name'], ['number']);

        return $this->shape($query, 'document', 'name', null, null, $term, $locale, 'number');
    }

    private function projects(string $term, string $locale): QueryBuilder
    {
        $query = Project::query()->public();
        $this->whereMatches($query, $term, $locale, ['title', 'summary', 'body']);

        return $this->shape($query, 'project', 'title', 'summary', 'slug', $term, $locale);
    }

    private function announcements(string $term, string $locale): QueryBuilder
    {
        $query = Announcement::query()->public();
        $this->whereMatches($query, $term, $locale, ['title', 'body'], ['org']);

        return $this->shape($query, 'announcement', 'title', 'body', 'slug', $term, $locale);
    }

    private function pages(string $term, string $locale): QueryBuilder
    {
        $query = Page::query()->public();
        $this->whereMatches($query, $term, $locale, ['title', 'body']);

        return $this->shape($query, 'page', 'title', 'body', 'slug', $term, $locale);
    }

    /**
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
                $pattern = "%{$variant}%";
                foreach ($translatedFields as $field) {
                    $where->orWhere("{$field}->{$locale}", 'like', $pattern);
                }
                foreach ($plainFields as $field) {
                    $where->orWhere($field, 'like', $pattern);
                }
            }
        });
    }

    private function shape(
        Builder $query,
        string $type,
        string $titleField,
        ?string $excerptField,
        ?string $keyField,
        string $term,
        string $locale,
        ?string $plainExcerptField = null,
    ): QueryBuilder {
        $title = $this->localizedExpression($query, $titleField, $locale);
        $excerpt = $excerptField !== null
            ? $this->localizedExpression($query, $excerptField, $locale)
            : ($plainExcerptField !== null ? $this->plainExpression($query, $plainExcerptField) : "''");
        $key = $keyField !== null ? $query->getQuery()->getGrammar()->wrap($keyField) : 'NULL';
        $publishedAt = $query->getQuery()->getGrammar()->wrap('published_at');

        return $query
            ->selectRaw('? as type', [$type])
            ->selectRaw("{$title} as title")
            ->selectRaw("{$excerpt} as excerpt")
            ->selectRaw("{$key} as resource_key")
            ->selectRaw("{$publishedAt} as published_at")
            ->selectRaw("CASE WHEN {$title} LIKE ? THEN 2 ELSE 1 END as relevance", ["%{$term}%"])
            ->toBase();
    }

    private function localizedExpression(Builder $query, string $field, string $locale): string
    {
        $grammar = $query->getQuery()->getGrammar();
        $requested = $grammar->wrap("{$field}->{$locale}");
        $russian = $grammar->wrap("{$field}->ru");

        return "COALESCE(NULLIF({$requested}, ''), NULLIF({$russian}, ''), '')";
    }

    private function plainExpression(Builder $query, string $field): string
    {
        return 'COALESCE('.$query->getQuery()->getGrammar()->wrap($field).", '')";
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
