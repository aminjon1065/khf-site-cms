<?php

namespace Database\Seeders;

use App\Models\MediaAsset;
use App\Models\User;
use Database\Seeders\Concerns\SeedsFromSource;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Fills the media library from the photo galleries attached to the scraped
 * material, so the media manager has a grid worth paging through, a mix of
 * described and decorative images, and real filenames to search by.
 *
 * Without network access (or with `SEED_SOURCE_MEDIA=false`) nothing is
 * created: a media asset with no file behind it would be worse than none.
 */
class MediaAssetSeeder extends Seeder
{
    use SeedsFromSource;

    /**
     * Keeps the library representative without turning seeding into a download
     * session — covers already arrive with the news themselves.
     */
    private const LIMIT = 36;

    public function run(): void
    {
        $uploaderId = User::query()->where('email', 'a.usmonov@khf.tj')->value('id');

        foreach ($this->galleryImages() as $index => $image) {
            $path = $this->sourceFilePath($image['url']);

            if ($path === null) {
                continue;
            }

            $title = $this->sourceFileName($image['url']);

            $asset = MediaAsset::query()->firstOrNew(['title' => $title]);
            $asset->fill([
                // Каждое четвёртое — декоративное: у такого изображения alt
                // намеренно пустой, и редактор должен это видеть в медиатеке.
                'alt' => $index % 4 === 3 ? null : $image['alt'],
                'caption' => $index % 3 === 0 ? $image['caption'] : null,
                'is_decorative' => $index % 4 === 3,
                'uploaded_by' => is_int($uploaderId) ? $uploaderId : null,
            ])->save();

            if (! $asset->hasMedia('asset')) {
                $asset->addMedia($path)
                    ->preservingOriginal()
                    ->usingFileName($title)
                    ->toMediaCollection('asset');
            }
        }
    }

    /**
     * Gallery images from both source locales, newest material first.
     *
     * @return array<int, array{url: string, alt: string, caption: string}>
     */
    private function galleryImages(): array
    {
        $items = [...$this->sourceItems('news-ru.json'), ...$this->sourceItems('news-tg.json')];

        usort(
            $items,
            static fn (array $a, array $b): int => strcmp((string) $b['published_at'], (string) $a['published_at']),
        );

        $images = [];

        foreach ($items as $item) {
            $gallery = $item['gallery'] ?? [];

            if (! is_array($gallery)) {
                continue;
            }

            foreach ($gallery as $url) {
                if (! is_string($url) || isset($images[$url])) {
                    continue;
                }

                $title = (string) $item['title'];

                $images[$url] = [
                    'url' => $url,
                    'alt' => Str::limit($title, 120),
                    'caption' => 'Из фотоматериалов к публикации «'.Str::limit($title, 70).'»',
                ];

                if (count($images) >= self::LIMIT) {
                    return array_values($images);
                }
            }
        }

        return array_values($images);
    }
}
