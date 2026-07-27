<?php

namespace App\Policies;

use App\Enums\Module;

/**
 * Authorization for the structure-units roster. Administrative reference
 * data — full CRUD limited to `structure.*` permission holders (admins),
 * with view granted to the chief editor (see `PermissionMatrix`).
 */
class StructureUnitPolicy extends ModulePolicy
{
    protected function module(): Module
    {
        return Module::Structure;
    }
}
