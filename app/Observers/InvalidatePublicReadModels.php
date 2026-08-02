<?php

namespace App\Observers;

use App\Http\Controllers\Api\SlugController;
use App\Models\Alert;
use App\Models\Announcement;
use App\Models\Category;
use App\Models\District;
use App\Models\HomeBlock;
use App\Models\Instruction;
use App\Models\MenuItem;
use App\Models\News;
use App\Models\Page;
use App\Models\Project;
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

    /**
     * Models whose rows appear in the slug-only listings served by
     * {@see SlugController}.
     *
     * @var list<class-string<Model>>
     */
    private const SLUG_SOURCES = [
        Alert::class,
        Announcement::class,
        Instruction::class,
        News::class,
        Page::class,
        Project::class,
    ];

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

        if (in_array($model::class, self::SLUG_SOURCES, true)) {
            $namespaces[] = PublicReadModelCache::SLUGS;
        }

        $this->cache->invalidate(...$namespaces);
    }
}
