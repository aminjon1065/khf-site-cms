<?php

namespace App\Support;

use App\Models\Alert;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class NavBadges
{
    /**
     * Sidebar badge counts for the given user.
     *
     * @return array<string, int>
     */
    public static function for(?User $user): array
    {
        if (! $user) {
            return [];
        }

        $badges = [
            'alerts' => Alert::query()->accessibleTo($user)->active()->count(),
        ];

        $permissionNames = $user->getAllPermissions()->pluck('name');
        $approval = 0;

        foreach (ContentTypes::MAP as $type => $modelClass) {
            if (! $permissionNames->contains(ContentTypes::module($type).'.approve')) {
                continue;
            }

            $query = $modelClass::query()
                ->whereIn('status', ['review', 'translation_check']);

            if ($user->hasRole('regional_editor')) {
                if ($user->region_id === null) {
                    $query->whereRaw('1 = 0');
                } elseif ($modelClass === Alert::class) {
                    $query
                        ->where('territory_type', 'regions')
                        ->whereHas('regions', fn (Builder $regions): Builder => $regions->whereKey($user->region_id));
                } else {
                    $query->where('author_id', $user->id);
                }
            }

            $approval += $query->count();
        }

        $badges['approval'] = $approval;

        return array_filter($badges, fn (int $count): bool => $count > 0);
    }
}
