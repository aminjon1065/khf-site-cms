<?php

namespace App\Policies;

use App\Enums\Module;

/**
 * Authorization for the regional reference data (regions & districts).
 * Reference geography is administrative data — full CRUD is limited to holders
 * of the `regions.*` permissions (admins), with view granted to operational
 * roles that consult regional units.
 */
class RegionPolicy extends ModulePolicy
{
    protected function module(): Module
    {
        return Module::Regions;
    }
}
