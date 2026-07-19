<?php

namespace App\Models;

use Illuminate\Support\Facades\Request;
use Spatie\Activitylog\Models\Activity as SpatieActivity;

/**
 * Custom activity model that records the request IP / user-agent and a
 * `is_critical` flag on top of Spatie's activity log.
 *
 * @property bool $is_critical
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property string|null $location
 */
class Activity extends SpatieActivity
{
    protected static function booted(): void
    {
        static::creating(function (self $activity): void {
            if (app()->runningInConsole() && ! app()->runningUnitTests()) {
                return;
            }

            if ($activity->ip_address === null) {
                $activity->ip_address = Request::ip();
            }

            if ($activity->user_agent === null) {
                $activity->user_agent = substr((string) Request::userAgent(), 0, 255);
            }
        });
    }

    protected function casts(): array
    {
        return array_merge(parent::casts(), [
            'is_critical' => 'boolean',
        ]);
    }
}
