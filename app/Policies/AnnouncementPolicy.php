<?php

namespace App\Policies;

use App\Enums\Module;

class AnnouncementPolicy extends ModulePolicy
{
    protected function module(): Module
    {
        return Module::Announcements;
    }
}
