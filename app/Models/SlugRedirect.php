<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * A former address of a material (see RemembersOldSlugs).
 *
 * @property int $id
 * @property string $redirectable_type
 * @property int $redirectable_id
 * @property string $old_slug
 * @property Carbon|null $created_at
 */
class SlugRedirect extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'redirectable_type',
        'redirectable_id',
        'old_slug',
    ];

    /**
     * @return MorphTo<Model, $this>
     */
    public function redirectable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Record a changed address. The new one now leads to the material itself,
     * so a redirect from it — this material's earlier one or another's — is
     * dropped; the old one points here, even if it once led elsewhere.
     */
    public static function remember(Model $material, string $oldSlug, string $newSlug): void
    {
        $type = $material->getMorphClass();

        self::query()->where('redirectable_type', $type)->where('old_slug', $newSlug)->delete();

        if ($oldSlug === '' || $oldSlug === $newSlug) {
            return;
        }

        self::query()->updateOrCreate(
            ['redirectable_type' => $type, 'old_slug' => $oldSlug],
            ['redirectable_id' => $material->getKey()],
        );
    }
}
