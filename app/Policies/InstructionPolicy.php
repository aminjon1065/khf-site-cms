<?php

namespace App\Policies;

use App\Enums\Module;

class InstructionPolicy extends ModulePolicy
{
    protected function module(): Module
    {
        return Module::Instructions;
    }
}
