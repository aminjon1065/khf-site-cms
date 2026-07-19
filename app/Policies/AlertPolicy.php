<?php

namespace App\Policies;

use App\Enums\Module;
use App\Models\Alert;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class AlertPolicy extends ModulePolicy
{
    protected function module(): Module
    {
        return Module::Alerts;
    }

    protected function belongsToUserRegion(User $user, Model $model): bool
    {
        if (! $model instanceof Alert) {
            return true;
        }

        return $model->territory_type === 'country'
            || $model->regions()->whereKey($user->region_id)->exists();
    }
}
