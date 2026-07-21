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
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SearchController extends Controller
{
    /**
     * Search across all publicly visible editorial content.
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

        $results = collect()
            ->concat($this->news($term, $locale))
            ->concat($this->alerts($term, $locale))
            ->concat($this->instructions($term, $locale))
            ->concat($this->documents($term, $locale))
            ->concat($this->projects($term, $locale))
            ->concat($this->announcements($term, $locale))
            ->concat($this->pages($term, $locale))
            ->sortByDesc('published_at')
            ->values();

        $total = $results->count();
        $items = $results->forPage($page, $perPage)->values()->all();

        return response()->json([
            'data' => $items,
            'meta' => [
                'current_page' => $page,
                'last_page' => max((int) ceil($total / $perPage), 1),
                'per_page' => $perPage,
                'total' => $total,
            ],
        ]);
    }

    /** @return Collection<int, array<string, mixed>> */
    private function news(string $term, string $locale): Collection
    {
        return News::query()->public()->latest('published_at')->limit(500)->get()
            ->filter(fn (News $news): bool => $this->matches($news, $term, ['title', 'summary', 'body']))
            ->map(fn (News $news): array => $this->result(
                'news',
                $this->translation($news, 'title', $locale),
                $this->translation($news, 'summary', $locale),
                "/news/{$news->slug}",
                $news->published_at?->toIso8601String(),
            ));
    }

    /** @return Collection<int, array<string, mixed>> */
    private function alerts(string $term, string $locale): Collection
    {
        return Alert::query()->public()->latest('published_at')->limit(500)->get()
            ->filter(fn (Alert $alert): bool => $this->matches($alert, $term, ['internal_title', 'title', 'summary', 'body']))
            ->map(fn (Alert $alert): array => $this->result(
                'alert',
                $this->translation($alert, 'title', $locale) ?: $alert->internal_title,
                $this->translation($alert, 'summary', $locale),
                "/alerts/{$alert->slug}",
                $alert->published_at?->toIso8601String(),
            ));
    }

    /** @return Collection<int, array<string, mixed>> */
    private function instructions(string $term, string $locale): Collection
    {
        return Instruction::query()->public()->latest('published_at')->limit(500)->get()
            ->filter(fn (Instruction $instruction): bool => $this->matches($instruction, $term, ['name', 'summary', 'body']))
            ->map(fn (Instruction $instruction): array => $this->result(
                'instruction',
                $this->translation($instruction, 'name', $locale),
                $this->translation($instruction, 'summary', $locale),
                "/guides/{$instruction->slug}",
                $instruction->published_at?->toIso8601String(),
            ));
    }

    /** @return Collection<int, array<string, mixed>> */
    private function documents(string $term, string $locale): Collection
    {
        return Document::query()->public()->latest('published_at')->limit(500)->get()
            ->filter(fn (Document $document): bool => $this->matches($document, $term, ['name', 'number']))
            ->map(fn (Document $document): array => $this->result(
                'document',
                $this->translation($document, 'name', $locale),
                $document->number ?? '',
                '/documents',
                $document->published_at?->toIso8601String(),
            ));
    }

    /** @return Collection<int, array<string, mixed>> */
    private function projects(string $term, string $locale): Collection
    {
        return Project::query()->public()->latest('published_at')->limit(500)->get()
            ->filter(fn (Project $project): bool => $this->matches($project, $term, ['title', 'summary', 'body']))
            ->map(fn (Project $project): array => $this->result(
                'project',
                $this->translation($project, 'title', $locale),
                $this->translation($project, 'summary', $locale),
                "/projects/{$project->slug}",
                $project->published_at?->toIso8601String(),
            ));
    }

    /** @return Collection<int, array<string, mixed>> */
    private function announcements(string $term, string $locale): Collection
    {
        return Announcement::query()->public()->latest('published_at')->limit(500)->get()
            ->filter(fn (Announcement $announcement): bool => $this->matches($announcement, $term, ['title', 'body', 'org']))
            ->map(fn (Announcement $announcement): array => $this->result(
                'announcement',
                $this->translation($announcement, 'title', $locale),
                $this->translation($announcement, 'body', $locale),
                '/announcements',
                $announcement->published_at?->toIso8601String(),
            ));
    }

    /** @return Collection<int, array<string, mixed>> */
    private function pages(string $term, string $locale): Collection
    {
        return Page::query()->public()->latest('published_at')->limit(500)->get()
            ->filter(fn (Page $page): bool => $this->matches($page, $term, ['title', 'body']))
            ->map(fn (Page $page): array => $this->result(
                'page',
                $this->translation($page, 'title', $locale),
                $this->translation($page, 'body', $locale),
                "/{$page->slug}",
                $page->published_at?->toIso8601String(),
            ));
    }

    /**
     * @param  list<string>  $fields
     */
    private function matches(Model $model, string $term, array $fields): bool
    {
        $needle = Str::lower($term);

        foreach ($fields as $field) {
            $value = $model->getRawOriginal($field);
            if (is_string($value) && str_starts_with($value, '{')) {
                $decoded = json_decode($value, true);
                $value = is_array($decoded) ? $decoded : $value;
            }

            $haystack = is_array($value)
                ? implode(' ', array_filter($value, 'is_string'))
                : (is_string($value) ? $value : '');

            if (Str::contains(Str::lower(strip_tags($haystack)), $needle)) {
                return true;
            }
        }

        return false;
    }

    /** @return array<string, mixed> */
    private function result(string $type, string $title, string $excerpt, string $path, ?string $publishedAt): array
    {
        return [
            'type' => $type,
            'title' => $title,
            'excerpt' => Str::limit(trim(strip_tags($excerpt)), 220),
            'path' => $path,
            'published_at' => $publishedAt,
        ];
    }

    private function translation(
        Alert|Announcement|Document|Instruction|News|Page|Project $model,
        string $field,
        string $locale,
    ): string {
        $value = $model->getTranslation($field, $locale, false);

        return $value !== '' ? $value : $model->getTranslation($field, 'ru', false);
    }
}
