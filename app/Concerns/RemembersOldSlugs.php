<?php

namespace App\Concerns;

use App\Models\SlugRedirect;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * A material whose address changes keeps its former one: the public API
 * answers it with a permanent redirect (App\Support\PublicSlug), so links
 * already shared don't turn into «page not found».
 */
trait RemembersOldSlugs
{
    public static function bootRemembersOldSlugs(): void
    {
        static::updated(function (Model $material): void {
            if (! $material->wasChanged('slug')) {
                return;
            }

            SlugRedirect::remember(
                $material,
                (string) $material->getOriginal('slug'),
                (string) $material->getAttribute('slug'),
            );
        });

        static::deleted(function (Model $material): void {
            if (method_exists($material, 'isForceDeleting') && ! $material->isForceDeleting()) {
                return;
            }

            SlugRedirect::query()->whereMorphedTo('redirectable', $material)->delete();
        });
    }

    /**
     * @return MorphMany<SlugRedirect, $this>
     */
    public function slugRedirects(): MorphMany
    {
        return $this->morphMany(SlugRedirect::class, 'redirectable');
    }
}
