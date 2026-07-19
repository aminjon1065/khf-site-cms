<?php

namespace App\Policies;

use App\Enums\Module;

class UserPolicy extends ModulePolicy
{
    protected function module(): Module
    {
        return Module::Users;
    }
}
