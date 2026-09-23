<?php

namespace App\Support;

use App\Models\SlugRedirect;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\RedirectResponse;

/**
 * A material of the public API by its address. A former address of a
 * material the site may show (RemembersOldSlugs) is answered with a
 * permanent redirect to the same endpoint under the current one; the site
 * follows it and sends the visitor to the canonical page.
 */
final class PublicSlug
{
    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $visible  What the site may show, without a slug condition
     * @return TModel
     *
     * @throws HttpResponseException 301 to the material's current address
     * @throws ModelNotFoundException<TModel>
     */
    public static function find(Builder $visible, string $slug): Model
    {
        $material = (clone $visible)->where('slug', $slug)->first();

        if ($material !== null) {
            return $material;
        }

        $current = self::currentSlug($visible, $slug);
        $route = request()->route()?->getName();

        if ($current !== null && $route !== null) {
            // Relative, so the site follows it on whatever host it calls the
            // API by.
            throw new HttpResponseException(new RedirectResponse(
                route($route, ['slug' => $current, ...request()->query()], false),
                301,
            ));
        }

        throw (new ModelNotFoundException)->setModel($visible->getModel()::class, [$slug]);
    }

    /**
     * @param  Builder<covariant Model>  $visible
     */
    private static function currentSlug(Builder $visible, string $oldSlug): ?string
    {
        $materialId = SlugRedirect::query()
            ->where('redirectable_type', $visible->getModel()->getMorphClass())
            ->where('old_slug', $oldSlug)
            ->value('redirectable_id');

        if ($materialId === null) {
            return null;
        }

        $slug = (clone $visible)->setEagerLoads([])->whereKey($materialId)->value('slug');

        return is_string($slug) && $slug !== '' && $slug !== $oldSlug ? $slug : null;
    }
}
