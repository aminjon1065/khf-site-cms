<?php

namespace App\Policies;

use App\Enums\Module;

/**
 * Authorization for the leadership roster. Administrative, high-visibility
 * data — full CRUD limited to `leadership.*` permission holders (admins),
 * with view granted to the chief editor (see `PermissionMatrix`).
 */
class LeaderPolicy extends ModulePolicy
{
    protected function module(): Module
    {
        return Module::Leadership;
    }
}
