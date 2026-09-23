<?php

namespace App\Concerns;

use App\Enums\ContentStatus;
use App\Support\StoredValue;
use Illuminate\Support\Carbon;

/**
 * `content_updated_at`: when the text of a material that was already on the
 * site really changed — the site's NewsArticle.dateModified and the page's
 * modified time. Saving without edits, a status change, the view counter or
 * editing a draft don't count; neither does the save that first publishes.
 */
trait TracksContentEdits
{
    /**
     * Columns whose change is a change of the material's content.
     *
     * @return list<string>
     */
    abstract protected function contentColumns(): array;

    public static function bootTracksContentEdits(): void
    {
        static::saving(function (self $material): void {
            if (! $material->exists || ! self::wasLive($material)) {
                return;
            }

            foreach ($material->contentColumns() as $column) {
                if ($material->isDirty($column)
                    && ! StoredValue::same($material->getRawOriginal($column), $material->getAttributes()[$column] ?? null)) {
                    $material->setAttribute('content_updated_at', now());

                    return;
                }
            }
        });
    }

    private static function wasLive(self $material): bool
    {
        $status = $material->getRawOriginal('status');
        $publishedAt = $material->getRawOriginal('published_at');

        return is_string($status)
            && (ContentStatus::tryFrom($status)?->isPublic() ?? false)
            && $publishedAt !== null
            && Carbon::parse($publishedAt)->isPast();
    }
}
