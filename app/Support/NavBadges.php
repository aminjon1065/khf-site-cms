<?php

namespace App\Support;

use App\Models\Alert;
use App\Models\Document;
use App\Models\Instruction;
use App\Models\News;
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
            'alerts' => Alert::query()->active()->count(),
        ];

        $approval = 0;

        if ($user->can('alerts.approve')) {
            $approval += Alert::query()->whereIn('status', ['review', 'translation_check'])->count();
        }
        if ($user->can('news.approve')) {
            $approval += News::query()->where('status', 'review')->count();
        }
        if ($user->can('instructions.approve')) {
            $approval += Instruction::query()->where('status', 'review')->count();
        }
        if ($user->can('documents.approve')) {
            $approval += Document::query()->where('status', 'review')->count();
        }

        $badges['approval'] = $approval;

        return array_filter($badges, fn (int $count): bool => $count > 0);
    }
}
