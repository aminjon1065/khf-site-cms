<?php

namespace App\Http\Controllers\Cms;

use App\Http\Controllers\Controller;
use App\Http\Requests\StructureUnit\StructureUnitRequest;
use App\Jobs\RevalidateFrontend;
use App\Models\StructureUnit;
use App\Support\FrontendRevalidation;
use App\Support\StructureTree;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Admin CRUD for the Committee's structure (C-1b): the units shown on the
 * public "Structure" page, nested to any depth — a main directorate, its
 * directorates, their departments. No workflow — a saved row is immediately
 * live, the same as `Region`/`Leader`.
 */
class StructureUnitController extends Controller
{
    public function index(): Response
    {
        $this->authorize('viewAny', StructureUnit::class);

        $units = array_map(fn (array $row): array => [
            'id' => $row['unit']->id,
            'parent_id' => $row['unit']->parent_id,
            'depth' => $row['depth'],
            'num' => $row['unit']->num,
            'name' => $row['unit']->getTranslation('name', 'ru'),
            'sort' => $row['unit']->sort,
            'children_count' => $row['unit']->getRelation('children')->count(),
        ], StructureTree::flatten($this->tree()));

        return Inertia::render('structure/index', ['units' => $units]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', StructureUnit::class);

        $parentId = $request->integer('parent');

        return Inertia::render('structure/form', [
            'unit' => null,
            'defaultParentId' => StructureUnit::query()->whereKey($parentId)->exists() ? $parentId : null,
            'parents' => $this->parentOptions(null),
        ]);
    }

    public function edit(StructureUnit $structureUnit): Response
    {
        $this->authorize('update', $structureUnit);

        return Inertia::render('structure/form', [
            'unit' => $this->payload($structureUnit),
            'defaultParentId' => null,
            'parents' => $this->parentOptions($structureUnit),
        ]);
    }

    public function store(StructureUnitRequest $request): RedirectResponse
    {
        $this->authorize('create', StructureUnit::class);

        $unit = new StructureUnit;
        $this->fill($unit, $request);
        $unit->save();

        RevalidateFrontend::forPayload(FrontendRevalidation::forReference('structure'));

        return redirect('/structure')->with('success', 'Подразделение добавлено.');
    }

    public function update(StructureUnitRequest $request, StructureUnit $structureUnit): RedirectResponse
    {
        $this->authorize('update', $structureUnit);

        $this->fill($structureUnit, $request);
        $structureUnit->save();

        RevalidateFrontend::forPayload(FrontendRevalidation::forReference('structure'));

        return redirect('/structure')->with('success', 'Подразделение обновлено.');
    }

    public function destroy(StructureUnit $structureUnit): RedirectResponse
    {
        $this->authorize('delete', $structureUnit);

        if ($structureUnit->children()->exists()) {
            return redirect('/structure')->with('error', 'Сначала перенесите или удалите вложенные подразделения.');
        }

        $structureUnit->delete();

        RevalidateFrontend::forPayload(FrontendRevalidation::forReference('structure'));

        return redirect('/structure')->with('success', 'Подразделение удалено.');
    }

    // ---------------------------------------------------------------- helpers

    private function fill(StructureUnit $unit, StructureUnitRequest $request): void
    {
        $unit->fill([
            'parent_id' => $request->integer('parent_id') ?: null,
            'num' => $request->input('num'),
            'sort' => (int) $request->integer('sort'),
        ]);

        foreach (['name', 'desc'] as $field) {
            /** @var array<string, string|null> $values */
            $values = $request->input($field, []);
            $unit->setTranslations($field, array_filter(
                $values,
                fn (?string $v): bool => $v !== null && trim($v) !== '',
            ));
        }
    }

    /**
     * @return Collection<int, StructureUnit>
     */
    private function tree(): Collection
    {
        return StructureTree::build(StructureUnit::query()->ordered()->get());
    }

    /**
     * Units the given one may be placed under: every unit except itself and
     * its own subunits, in tree order and indented by depth.
     *
     * @return list<array{value: int, label: string}>
     */
    private function parentOptions(?StructureUnit $current): array
    {
        $options = [];
        $subtreeDepth = null;

        foreach (StructureTree::flatten($this->tree()) as ['unit' => $unit, 'depth' => $depth]) {
            if ($subtreeDepth !== null && $depth > $subtreeDepth) {
                continue;
            }

            $subtreeDepth = null;

            if ($current !== null && $unit->is($current)) {
                $subtreeDepth = $depth;

                continue;
            }

            $options[] = [
                'value' => $unit->id,
                'label' => str_repeat('— ', $depth).trim($unit->num.' '.$unit->getTranslation('name', 'ru')),
            ];
        }

        return $options;
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(StructureUnit $unit): array
    {
        return [
            'id' => $unit->id,
            'parent_id' => $unit->parent_id,
            'num' => $unit->num,
            'name' => $unit->getTranslations('name'),
            'desc' => $unit->getTranslations('desc'),
            'sort' => $unit->sort,
            'languages' => $unit->languageCompleteness(),
        ];
    }
}
