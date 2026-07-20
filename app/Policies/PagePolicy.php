<?php

namespace App\Policies;

use App\Enums\Module;

/**
 * Authorization for site content pages (informational pages managed under the
 * editorial workflow).
 */
class PagePolicy extends ModulePolicy
{
    protected function module(): Module
    {
        return Module::Pages;
    }
}
