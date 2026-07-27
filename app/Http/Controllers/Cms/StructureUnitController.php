<?php

namespace App\Http\Controllers\Cms;

use App\Http\Controllers\Controller;
use App\Http\Requests\StructureUnit\StructureUnitRequest;
use App\Models\StructureUnit;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Admin CRUD for the structure-units roster (C-1b): the specialised
 * departments shown on the public "Structure" page. No workflow — a saved
 * row is immediately live, the same as `Region`/`Leader`.
 */
class StructureUnitController extends Controller
{
    public function index(): Response
    {
        $this->authorize('viewAny', StructureUnit::class);

        $units = StructureUnit::query()->ordered()->get()->map(fn (StructureUnit $unit): array => [
            'id' => $unit->id,
            'num' => $unit->num,
            'name' => $unit->getTranslation('name', 'ru'),
            'sort' => $unit->sort,
        ]);

        return Inertia::render('structure/index', ['units' => $units->all()]);
    }

    public function create(): Response
    {
        $this->authorize('create', StructureUnit::class);

        return Inertia::render('structure/form', ['unit' => null]);
    }

    public function edit(StructureUnit $structureUnit): Response
    {
        $this->authorize('update', $structureUnit);

        return Inertia::render('structure/form', ['unit' => $this->payload($structureUnit)]);
    }

    public function store(StructureUnitRequest $request): RedirectResponse
    {
        $this->authorize('create', StructureUnit::class);

        $unit = new StructureUnit;
        $this->fill($unit, $request);
        $unit->save();

        return redirect('/structure')->with('success', 'Подразделение добавлено.');
    }

    public function update(StructureUnitRequest $request, StructureUnit $structureUnit): RedirectResponse
    {
        $this->authorize('update', $structureUnit);

        $this->fill($structureUnit, $request);
        $structureUnit->save();

        return redirect('/structure')->with('success', 'Подразделение обновлено.');
    }

    public function destroy(StructureUnit $structureUnit): RedirectResponse
    {
        $this->authorize('delete', $structureUnit);
        $structureUnit->delete();

        return redirect('/structure')->with('success', 'Подразделение удалено.');
    }

    // ---------------------------------------------------------------- helpers

    private function fill(StructureUnit $unit, StructureUnitRequest $request): void
    {
        $unit->fill([
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
     * @return array<string, mixed>
     */
    private function payload(StructureUnit $unit): array
    {
        return [
            'id' => $unit->id,
            'num' => $unit->num,
            'name' => $unit->getTranslations('name'),
            'desc' => $unit->getTranslations('desc'),
            'sort' => $unit->sort,
            'languages' => $unit->languageCompleteness(),
        ];
    }
}
