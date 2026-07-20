<?php

namespace App\Policies;

use App\Enums\Module;

class SubmissionPolicy extends ModulePolicy
{
    protected function module(): Module
    {
        return Module::Submissions;
    }
}
