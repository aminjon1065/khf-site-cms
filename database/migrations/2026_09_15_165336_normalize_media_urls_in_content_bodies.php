<?php

use App\Support\ContentMediaUrls;
use App\Support\ContentTypes;
use Illuminate\Database\Migrations\Migration;

/**
 * One-off data normalization: stored rich-text bodies referenced media
 * by absolute URL, so every host/port change broke the images. Rewrite
 * /storage/ origins to root-relative paths; the public API already
 * re-resolves images via data-media-id, so its output is unaffected.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (ContentTypes::MAP as $modelClass) {
            $model = new $modelClass;
            $bodyColumn = $model->getConnection()->getSchemaBuilder()->hasColumn(
                $model->getTable(),
                'body',
            );

            if (! $bodyColumn) {
                continue;
            }

            $locales = ['ru', 'tg', 'en'];

            $modelClass::query()
                ->where('body', 'like', '%://%/storage/%')
                ->chunkById(100, function ($chunk) use ($locales): void {
                    foreach ($chunk as $item) {
                        $dirty = false;

                        foreach ($locales as $locale) {
                            $html = $item->getTranslation('body', $locale, false);

                            if (! is_string($html) || $html === '') {
                                continue;
                            }

                            $normalized = ContentMediaUrls::relativizeHtml($html);

                            if ($normalized !== $html) {
                                $item->setTranslation('body', $locale, $normalized);
                                $dirty = true;
                            }
                        }

                        if ($dirty) {
                            $item->save();
                        }
                    }
                });
        }
    }

    public function down(): void
    {
        // Directional data fix: absolute → relative cannot be reversed
        // (the original origin is not recoverable), so this is a no-op.
    }
};
