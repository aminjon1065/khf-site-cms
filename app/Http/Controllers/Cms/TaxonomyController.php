<?php

namespace App\Http\Controllers\Cms;

use App\Http\Controllers\Controller;
use App\Http\Requests\Taxonomy\TaxonomyRequest;
use App\Models\Category;
use App\Models\Tag;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Single-page manager for editorial taxonomy: news categories (typed) and the
 * global tag vocabulary. Rows are synced on save (create / update / delete).
 */
class TaxonomyController extends Controller
{
    private const CATEGORY_TYPE = 'news';

    public function index(Request $request): Response
    {
        abort_unless((bool) $request->user()?->can('taxonomy.view'), 403);

        $categories = Category::query()
            ->where('type', self::CATEGORY_TYPE)
            ->withCount('news')
            ->orderBy('sort')
            ->get()
            ->map(fn (Category $c): array => [
                'id' => $c->id,
                'name' => $c->getTranslations('name'),
                'slug' => $c->slug,
                'sort' => $c->sort,
                'usage' => (int) $c->getAttribute('news_count'),
            ])->all();

        $tags = Tag::query()->orderBy('id')->get()->map(fn (Tag $t): array => [
            'id' => $t->id,
            'name' => $t->getTranslations('name'),
            'slug' => $t->slug,
        ])->all();

        return Inertia::render('taxonomy/index', [
            'categories' => $categories,
            'tags' => $tags,
        ]);
    }

    public function update(TaxonomyRequest $request): RedirectResponse
    {
        $this->authorizeChanges($request);

        DB::transaction(function () use ($request): void {
            $this->syncCategories($request);
            $this->syncTags($request);
        });

        return back()->with('success', 'Категории и теги сохранены.');
    }

    // ---------------------------------------------------------------- helpers

    private function authorizeChanges(TaxonomyRequest $request): void
    {
        $categoryRows = $this->meaningfulRows($request->input('categories', []));
        $tagRows = $this->meaningfulRows($request->input('tags', []));

        $createsTerms = collect([...$categoryRows, ...$tagRows])->contains(
            fn (array $row): bool => empty($row['id']),
        );

        $submittedCategoryIds = collect($categoryRows)->pluck('id')->filter()->map(fn (mixed $id): int => (int) $id);
        $submittedTagIds = collect($tagRows)->pluck('id')->filter()->map(fn (mixed $id): int => (int) $id);

        $deletesTerms = Category::query()
            ->where('type', self::CATEGORY_TYPE)
            ->whereNotIn('id', $submittedCategoryIds)
            ->exists()
            || Tag::query()->whereNotIn('id', $submittedTagIds)->exists();

        abort_if($createsTerms && ! $request->user()?->can('taxonomy.create'), 403);
        abort_if($deletesTerms && ! $request->user()?->can('taxonomy.delete'), 403);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function meaningfulRows(mixed $input): array
    {
        if (! is_array($input)) {
            return [];
        }

        return array_values(array_filter(
            $input,
            fn (mixed $row): bool => is_array($row)
                && trim((string) (($row['name']['ru'] ?? null))) !== '',
        ));
    }

    private function syncCategories(TaxonomyRequest $request): void
    {
        /** @var array<int, array<string, mixed>> $rows */
        $rows = $request->input('categories', []);
        $keep = [];

        foreach (array_values($rows) as $sort => $row) {
            $name = $this->localeMap(is_array($row['name'] ?? null) ? $row['name'] : []);

            if (($name['ru'] ?? '') === '') {
                continue; // drop rows without a Russian name
            }

            $category = ! empty($row['id'])
                ? Category::query()->where('type', self::CATEGORY_TYPE)->whereKey((int) $row['id'])->first()
                : null;
            $category ??= new Category;

            $category->type = self::CATEGORY_TYPE;
            // replaceTranslations (not setTranslations) so a cleared tg/en value
            // is actually removed rather than merged over the stored one.
            $category->replaceTranslations('name', $name);
            $category->slug = $this->uniqueCategorySlug($this->desiredSlug($row, $name['ru']), $category->id);
            $category->sort = $sort;
            $category->save();

            $keep[] = $category->id;
        }

        // FK on news.category_id is nullOnDelete, so removals detach cleanly.
        Category::query()->where('type', self::CATEGORY_TYPE)->whereNotIn('id', $keep)->delete();
    }

    private function syncTags(TaxonomyRequest $request): void
    {
        /** @var array<int, array<string, mixed>> $rows */
        $rows = $request->input('tags', []);
        $keep = [];

        foreach (array_values($rows) as $row) {
            $name = $this->localeMap(is_array($row['name'] ?? null) ? $row['name'] : []);

            if (($name['ru'] ?? '') === '') {
                continue;
            }

            $tag = ! empty($row['id']) ? Tag::query()->whereKey((int) $row['id'])->first() : null;
            $tag ??= new Tag;

            $tag->replaceTranslations('name', $name);
            $tag->slug = $this->uniqueTagSlug($this->desiredSlug($row, $name['ru']), $tag->id);
            $tag->save();

            $keep[] = $tag->id;
        }

        // taggables has cascadeOnDelete, so pivot rows are removed with the tag.
        Tag::query()->whereNotIn('id', $keep)->delete();
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function desiredSlug(array $row, string $ruName): string
    {
        $slug = is_string($row['slug'] ?? null) ? trim($row['slug']) : '';
        $source = $slug !== '' ? $slug : $ruName;

        return Str::slug($source, '-', 'ru') ?: 'term-'.Str::lower(Str::random(6));
    }

    private function uniqueCategorySlug(string $base, ?int $ignoreId): string
    {
        $slug = $base;
        $suffix = 2;

        while (Category::query()
            ->where('type', self::CATEGORY_TYPE)
            ->where('slug', $slug)
            ->when($ignoreId !== null, fn ($q) => $q->whereKeyNot($ignoreId))
            ->exists()) {
            $slug = $base.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }

    private function uniqueTagSlug(string $base, ?int $ignoreId): string
    {
        $slug = $base;
        $suffix = 2;

        while (Tag::query()
            ->where('slug', $slug)
            ->when($ignoreId !== null, fn ($q) => $q->whereKeyNot($ignoreId))
            ->exists()) {
            $slug = $base.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }

    /**
     * @return array<string, string>
     */
    private function localeMap(mixed $input): array
    {
        if (! is_array($input)) {
            return [];
        }

        $map = [];
        foreach (['ru', 'tg', 'en'] as $locale) {
            $value = $input[$locale] ?? null;
            if (is_string($value) && trim($value) !== '') {
                $map[$locale] = $value;
            }
        }

        return $map;
    }
}
