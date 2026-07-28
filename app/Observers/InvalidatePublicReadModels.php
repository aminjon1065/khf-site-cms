<?php

namespace App\Observers;

use App\Models\Alert;
use App\Models\Category;
use App\Models\District;
use App\Models\HomeBlock;
use App\Models\MenuItem;
use App\Models\Region;
use App\Models\Setting;
use App\Services\PublicReadModelCache;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

final class InvalidatePublicReadModels implements ShouldHandleEventsAfterCommit
{
    public function __construct(private readonly PublicReadModelCache $cache) {}

    public function saved(Model $model): void
    {
        $this->invalidate($model);
    }

    public function deleted(Model $model): void
    {
        $this->invalidate($model);
    }

    public function restored(Model $model): void
    {
        $this->invalidate($model);
    }

    public function forceDeleted(Model $model): void
    {
        $this->invalidate($model);
    }

    private function invalidate(Model $model): void
    {
        $namespaces = match (true) {
            $model instanceof Setting => [
                PublicReadModelCache::SETTINGS,
                PublicReadModelCache::HOME,
            ],
            $model instanceof MenuItem => [PublicReadModelCache::MENU],
            $model instanceof Category => [
                PublicReadModelCache::CATEGORIES,
                PublicReadModelCache::HOME,
            ],
            $model instanceof HomeBlock => [PublicReadModelCache::HOME],
            $model instanceof Alert => [
                PublicReadModelCache::ALERTS,
                PublicReadModelCache::REGIONS,
                PublicReadModelCache::HOME,
            ],
            $model instanceof Region, $model instanceof District => [
                PublicReadModelCache::REGIONS,
                PublicReadModelCache::ALERTS,
                PublicReadModelCache::HOME,
            ],
            $model instanceof Media => [PublicReadModelCache::HOME],
            default => [PublicReadModelCache::HOME],
        };

        $this->cache->invalidate(...$namespaces);
    }
}
