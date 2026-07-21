<?php

namespace App\Concerns;

use App\Enums\RoleName;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

trait HasRegionalContentScope
{
    /**
     * Regionless editorial records are owned by their author. Regional
     * editors may only browse records they created themselves.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeAccessibleTo(Builder $query, ?User $user): Builder
    {
        if (! $user?->hasRole(RoleName::RegionalEditor->value)) {
            return $query;
        }

        if ($user->region_id === null) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where('author_id', $user->id);
    }
}
