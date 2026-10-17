<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * An address of the previous Drupal sites that leads to a material now in the
 * CMS (see the legacy_redirects migration).
 *
 * @property int $id
 * @property string $source
 * @property string $redirectable_type
 * @property int $redirectable_id
 * @property string $locale
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class LegacyRedirect extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'source',
        'redirectable_type',
        'redirectable_id',
        'locale',
    ];

    /**
     * @return MorphTo<Model, $this>
     */
    public function redirectable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * `https://khf.tj/node/6112?x=1` → `khf.tj/node/6112`: the form both the
     * importer and the site's lookup use, so `www.`, the scheme, a trailing
     * slash or a query string do not make one old page into several.
     */
    public static function normalize(string $address): ?string
    {
        $parts = parse_url(str_contains($address, '://') ? $address : 'https://'.$address);
        $host = strtolower((string) ($parts['host'] ?? ''));
        $path = rtrim((string) ($parts['path'] ?? ''), '/');

        if ($host === '' || $path === '') {
            return null;
        }

        return preg_replace('/^www\./', '', $host).$path;
    }

    /**
     * Point an old address at a material. Repeating the import keeps one row
     * per address.
     */
    public static function remember(string $address, Model $material, string $locale): void
    {
        $source = self::normalize($address);

        if ($source === null) {
            return;
        }

        self::query()->updateOrCreate(
            ['source' => $source],
            [
                'redirectable_type' => $material->getMorphClass(),
                'redirectable_id' => $material->getKey(),
                'locale' => $locale,
            ],
        );
    }
}
