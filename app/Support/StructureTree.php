<?php

namespace App\Support;

use App\Models\StructureUnit;
use Illuminate\Database\Eloquent\Collection;

/**
 * Builds the Committee's structure tree from a flat, display-ordered list of
 * units, so every screen and the public API nest them the same way.
 */
final class StructureTree
{
    /**
     * Nests units under their parents, keeping each level in the given order.
     * The `children` relation is set on every unit, down to the leaves; a unit
     * whose parent is not in the list is kept at the top rather than dropped.
     *
     * @param  Collection<int, StructureUnit>  $units
     * @return Collection<int, StructureUnit>
     */
    public static function build(Collection $units): Collection
    {
        $ids = array_flip($units->modelKeys());
        $byParent = $units->groupBy(
            fn (StructureUnit $unit): int => isset($ids[$unit->parent_id]) ? (int) $unit->parent_id : 0,
        );

        $nest = function (StructureUnit $unit) use (&$nest, $byParent): StructureUnit {
            /** @var Collection<int, StructureUnit> $children */
            $children = new Collection($byParent->get($unit->id, []));

            return $unit->setRelation('children', $children->map($nest)->values());
        };

        return (new Collection($byParent->get(0, [])))->map($nest)->values();
    }

    /**
     * Depth-first rows of an already built tree, for lists and parent pickers.
     *
     * @param  Collection<int, StructureUnit>  $roots
     * @return list<array{unit: StructureUnit, depth: int}>
     */
    public static function flatten(Collection $roots, int $depth = 0): array
    {
        $rows = [];

        foreach ($roots as $unit) {
            $rows[] = ['unit' => $unit, 'depth' => $depth];

            /** @var Collection<int, StructureUnit> $children */
            $children = $unit->getRelation('children');
            array_push($rows, ...self::flatten($children, $depth + 1));
        }

        return $rows;
    }
}
