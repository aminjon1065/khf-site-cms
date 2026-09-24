<?php

namespace App\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

trait HasRegionalContentScope
{
    /**
     * Regionless editorial records are owned by their author. An account
     * limited to its region browses only the records it created.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeAccessibleTo(Builder $query, ?User $user): Builder
    {
        if ($user?->isLimitedToRegion() !== true) {
            return $query;
        }

        if ($user->region_id === null) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where('author_id', $user->id);
    }
}
