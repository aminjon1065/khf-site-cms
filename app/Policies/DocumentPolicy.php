<?php

namespace App\Policies;

use App\Enums\Module;

class DocumentPolicy extends ModulePolicy
{
    protected function module(): Module
    {
        return Module::Documents;
    }
}
