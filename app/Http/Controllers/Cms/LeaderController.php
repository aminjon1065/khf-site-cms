<?php

namespace App\Http\Controllers\Cms;

use App\Http\Controllers\Controller;
use App\Http\Requests\Leader\LeaderRequest;
use App\Models\Leader;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Admin CRUD for the leadership roster (C-1a): the chairman and deputy
 * chairmen shown on the public "Leadership" page. No workflow — a saved row
 * is immediately live, the same as `Region`.
 */
class LeaderController extends Controller
{
    public function index(): Response
    {
        $this->authorize('viewAny', Leader::class);

        $leaders = Leader::query()->ordered()->get()->map(fn (Leader $leader): array => [
            'id' => $leader->id,
            'role' => $leader->getTranslation('role', 'ru'),
            'name' => $leader->getTranslation('name', 'ru'),
            'is_chairman' => $leader->is_chairman,
            'sort' => $leader->sort,
            'photo_url' => $leader->getFirstMediaUrl('photo') ?: null,
        ]);

        return Inertia::render('leadership/index', ['leaders' => $leaders->all()]);
    }

    public function create(): Response
    {
        $this->authorize('create', Leader::class);

        return Inertia::render('leadership/form', ['leader' => null]);
    }

    public function edit(Leader $leader): Response
    {
        $this->authorize('update', $leader);

        $leader->load('media');

        return Inertia::render('leadership/form', ['leader' => $this->payload($leader)]);
    }

    public function store(LeaderRequest $request): RedirectResponse
    {
        $this->authorize('create', Leader::class);

        DB::transaction(function () use ($request): void {
            $leader = new Leader;
            $this->fill($leader, $request);
            $leader->save();
            $this->syncMedia($leader, $request);
        });

        return redirect('/leadership')->with('success', 'Запись добавлена.');
    }

    public function update(LeaderRequest $request, Leader $leader): RedirectResponse
    {
        $this->authorize('update', $leader);

        DB::transaction(function () use ($leader, $request): void {
            $this->fill($leader, $request);
            $leader->save();
            $this->syncMedia($leader, $request);
        });

        return redirect('/leadership')->with('success', 'Запись обновлена.');
    }

    public function destroy(Leader $leader): RedirectResponse
    {
        $this->authorize('delete', $leader);
        $leader->delete();

        return redirect('/leadership')->with('success', 'Запись удалена.');
    }

    // ---------------------------------------------------------------- helpers

    private function fill(Leader $leader, LeaderRequest $request): void
    {
        $leader->fill([
            'is_chairman' => $request->boolean('is_chairman'),
            'sort' => (int) $request->integer('sort'),
        ]);

        foreach (['role', 'name', 'meta', 'bio'] as $field) {
            /** @var array<string, string|null> $values */
            $values = $request->input($field, []);
            $leader->setTranslations($field, array_filter(
                $values,
                fn (?string $v): bool => $v !== null && trim($v) !== '',
            ));
        }
    }

    private function syncMedia(Leader $leader, LeaderRequest $request): void
    {
        $changed = false;

        if ($request->boolean('photo_remove')) {
            $leader->clearMediaCollection('photo');
            $changed = true;
        }

        if ($request->hasFile('photo')) {
            $leader->clearMediaCollection('photo');
            $leader->addMediaFromRequest('photo')->toMediaCollection('photo');
            $changed = true;
        } elseif ($request->filled('photo_media_id')) {
            // Chosen from the media library: copy the source file into this
            // leader's own `photo` collection so it is independent of the library.
            $source = Media::find($request->integer('photo_media_id'));
            if ($source !== null) {
                $leader->clearMediaCollection('photo');
                $source->copy($leader, 'photo');
                $changed = true;
            }
        }

        if ($changed) {
            // Media attach/detach doesn't touch Leader's own saved/deleted
            // events, so FlushesPublicCache wouldn't otherwise see a
            // photo-only change — touch() re-saves the model to trigger it.
            $leader->touch();
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Leader $leader): array
    {
        return [
            'id' => $leader->id,
            'role' => $leader->getTranslations('role'),
            'name' => $leader->getTranslations('name'),
            'meta' => $leader->getTranslations('meta'),
            'bio' => $leader->getTranslations('bio'),
            'is_chairman' => $leader->is_chairman,
            'sort' => $leader->sort,
            'photo_url' => $leader->getFirstMediaUrl('photo') ?: null,
            'languages' => $leader->languageCompleteness(),
        ];
    }
}
