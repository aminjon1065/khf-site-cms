<?php

namespace App\Support;

use App\Models\Alert;
use App\Models\User;

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

        $approval = 0;

        foreach (ContentTypes::MAP as $type => $modelClass) {
            if (! $user->can(ContentTypes::module($type).'.approve')) {
                continue;
            }

            $approval += $modelClass::query()
                ->whereIn('status', ['review', 'translation_check'])
                ->get()
                ->filter(fn ($model): bool => $user->can('approve', $model))
                ->count();
        }

        $badges['approval'] = $approval;

        return array_filter($badges, fn (int $count): bool => $count > 0);
    }
}
