<?php

namespace App\Http\Controllers\Cms;

use App\Enums\RegionType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Region\RegionRequest;
use App\Models\District;
use App\Models\Region;
use App\Services\AlertMapService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Admin CRUD for the regional reference data (top-level regions and their
 * curated districts). The live alert status shown in the list is computed from
 * active alerts — the stored `status` column is not a manual override.
 */
class RegionController extends Controller
{
    public function __construct(private readonly AlertMapService $map) {}

    public function index(): Response
    {
        $this->authorize('viewAny', Region::class);

        $statuses = collect($this->map->regionStatuses('ru'))->keyBy('key');

        // Alias the relation count so it does not clobber the real
        // `districts_count` column (the official total number of districts).
        $regions = Region::query()
            ->withCount(['districts as curated_count'])
            ->orderBy('sort')
            ->get()
            ->map(function (Region $region) use ($statuses): array {
                $status = $statuses->get($region->code);

                return [
                    'id' => $region->id,
                    'name' => $region->getTranslation('name', 'ru'),
                    'code' => $region->code,
                    'type' => $region->type->label(),
                    'regional_center' => $region->regional_center,
                    'phone' => $region->phone,
                    'districts_count' => $region->districts_count,
                    'curated_count' => (int) $region->getAttribute('curated_count'),
                    'level' => $status['level'] ?? 'none',
                    'active_count' => $status['count'] ?? 0,
                    'status_text' => $status['statusText'] ?? 'Обстановка штатная',
                ];
            });

        return Inertia::render('regions/index', ['regions' => $regions->all()]);
    }

    public function create(): Response
    {
        $this->authorize('create', Region::class);

        return Inertia::render('regions/form', [
            'region' => null,
            'reference' => $this->reference(),
        ]);
    }

    public function edit(Region $region): Response
    {
        $this->authorize('update', $region);

        return Inertia::render('regions/form', [
            'region' => $this->payload($region),
            'reference' => $this->reference(),
        ]);
    }

    public function store(RegionRequest $request): RedirectResponse
    {
        $this->authorize('create', Region::class);

        $region = new Region;
        $this->fill($region, $request);
        $region->save();
        $this->syncDistricts($region, $request);

        return redirect('/regions')->with('success', 'Регион создан.');
    }

    public function update(RegionRequest $request, Region $region): RedirectResponse
    {
        $this->authorize('update', $region);

        $this->fill($region, $request);
        $region->save();
        $this->syncDistricts($region, $request);

        return redirect('/regions')->with('success', 'Регион обновлён.');
    }

    public function destroy(Region $region): RedirectResponse
    {
        $this->authorize('delete', $region);

        if ($region->alerts()->exists()) {
            return back()->with('error', 'Нельзя удалить регион: он используется в предупреждениях.');
        }

        $region->delete();

        return redirect('/regions')->with('success', 'Регион удалён.');
    }

    // ---------------------------------------------------------------- helpers

    private function fill(Region $region, RegionRequest $request): void
    {
        $region->fill([
            'code' => $request->input('code'),
            'type' => $request->input('type'),
            'regional_center' => $request->input('regional_center'),
            'phone' => $request->input('phone'),
            'duty_phone' => $request->input('duty_phone'),
            'email' => $request->input('email'),
            'districts_count' => (int) $request->input('districts_count', 0),
            'sort' => (int) $request->input('sort', 0),
        ]);

        $region->setTranslations('name', $this->localeMap($request->input('name', [])));
        $region->setTranslations('head', $this->localeMap($request->input('head', [])));
        $region->setTranslations('address', $this->localeMap($request->input('address', [])));
    }

    /**
     * Create/update curated districts and delete rows removed in the editor.
     */
    private function syncDistricts(Region $region, RegionRequest $request): void
    {
        /** @var array<int, array<string, mixed>> $rows */
        $rows = $request->input('districts', []);
        $keep = [];

        foreach (array_values($rows) as $sort => $row) {
            $name = $this->localeMap(is_array($row['name'] ?? null) ? $row['name'] : []);

            if (($name['ru'] ?? '') === '') {
                continue; // drop rows without a Russian name
            }

            // Only districts already belonging to this region may be updated by
            // id; a foreign/unknown id falls through to a new district so a
            // crafted payload cannot reassign another region's district.
            $district = ! empty($row['id'])
                ? $region->districts()->whereKey((int) $row['id'])->first()
                : null;
            $district ??= new District;

            $district->region_id = $region->id;
            $district->setTranslations('name', $name);
            $district->sort = $sort;
            $district->save();

            $keep[] = $district->id;
        }

        $region->districts()->whereNotIn('id', $keep)->delete();
    }

    /**
     * Keep only the three supported locales and trim empty values.
     *
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

    /**
     * @return array<string, mixed>
     */
    private function payload(Region $region): array
    {
        return [
            'id' => $region->id,
            'name' => $region->getTranslations('name'),
            'head' => $region->getTranslations('head'),
            'code' => $region->code,
            'type' => $region->type->value,
            'regional_center' => $region->regional_center,
            'address' => $region->getTranslations('address'),
            'phone' => $region->phone,
            'duty_phone' => $region->duty_phone,
            'email' => $region->email,
            'districts_count' => $region->districts_count,
            'sort' => $region->sort,
            'districts' => $region->districts()->orderBy('sort')->get()->map(fn (District $d): array => [
                'id' => $d->id,
                'name' => $d->getTranslations('name'),
            ])->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function reference(): array
    {
        return [
            'types' => RegionType::options(),
        ];
    }
}
