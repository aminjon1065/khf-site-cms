<?php

namespace App\Policies;

use App\Enums\Module;

class ProjectPolicy extends ModulePolicy
{
    protected function module(): Module
    {
        return Module::Projects;
    }
}
