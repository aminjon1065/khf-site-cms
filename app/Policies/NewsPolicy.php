<?php

namespace App\Policies;

use App\Enums\Module;

class NewsPolicy extends ModulePolicy
{
    protected function module(): Module
    {
        return Module::News;
    }
}
