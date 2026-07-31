<?php

namespace App\Console\Commands;

use App\Enums\ContentStatus;
use App\Models\MediaAsset;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use JsonException;

class SeedProductionLikeContent extends Command
{
    protected $signature = 'benchmark:seed
        {--news=10000 : Number of deterministic published news rows}
        {--media=5000 : Number of deterministic benchmark media assets}
        {--media-bytes=524288000 : Total bytes reserved in benchmark storage}';

    protected $description = 'Create an isolated, deterministic production-like performance dataset';

    public function handle(): int
    {
        if (! $this->isSafeBenchmarkEnvironment()) {
            $this->components->error(
                'Refusing to seed: use APP_ENV=benchmark and a database name containing "benchmark".',
            );

            return self::FAILURE;
        }

        $newsCount = (int) $this->option('news');
        $mediaCount = (int) $this->option('media');
        $mediaBytes = (int) $this->option('media-bytes');

        if (! $this->validProfile($newsCount, $mediaCount, $mediaBytes)) {
            $this->components->error(
                'Invalid profile: news 1..100000, media 1..50000, media-bytes 512 bytes per asset..5 GiB.',
            );

            return self::INVALID;
        }

        $startedAt = hrtime(true);
        $this->seedNews($newsCount);
        $writtenBytes = $this->seedMedia($mediaCount, $mediaBytes);
        $durationSeconds = round((hrtime(true) - $startedAt) / 1_000_000_000, 3);
        $manifest = [
            'profile' => 'production-like',
            'news' => $newsCount,
            'media' => $mediaCount,
            'media_bytes' => $writtenBytes,
            'large_image_bytes' => $this->largeImageBytes($mediaCount, $mediaBytes),
            'image_dimensions' => ['width' => 8000, 'height' => 8000],
            'duration_seconds' => $durationSeconds,
        ];

        Storage::disk('benchmark')->put(
            'benchmark-manifest.json',
            $this->encode($manifest, pretty: true)."\n",
        );

        $this->components->info(
            "Benchmark ready: {$newsCount} news, {$mediaCount} media, {$writtenBytes} storage bytes in {$durationSeconds}s.",
        );

        return self::SUCCESS;
    }

    private function isSafeBenchmarkEnvironment(): bool
    {
        if (app()->environment('testing')) {
            return true;
        }

        $connection = (string) config('database.default');
        $database = (string) config("database.connections.{$connection}.database");

        return app()->environment('benchmark')
            && Str::contains(Str::lower($database), 'benchmark');
    }

    private function validProfile(int $newsCount, int $mediaCount, int $mediaBytes): bool
    {
        return $newsCount >= 1
            && $newsCount <= 100_000
            && $mediaCount >= 1
            && $mediaCount <= 50_000
            && $mediaBytes >= $mediaCount * 512
            && $mediaBytes <= 5 * 1024 * 1024 * 1024;
    }

    private function seedNews(int $count): void
    {
        $now = now();
        $longBody = '<p>'.str_repeat(
            'Детерминированный production-like текст для проверки rich-text rendering, поиска и pagination. ',
            220,
        ).'</p>';

        foreach (range(1, $count) as $chunkStart) {
            if (($chunkStart - 1) % 500 !== 0) {
                continue;
            }

            $rows = [];
            $chunkEnd = min($count, $chunkStart + 499);

            foreach (range($chunkStart, $chunkEnd) as $index) {
                $sequence = str_pad((string) $index, 6, '0', STR_PAD_LEFT);
                $title = [
                    'tg' => "Маводи санҷишӣ {$sequence}",
                    'ru' => "Тестовый материал {$sequence}",
                    'en' => "Benchmark article {$sequence}",
                ];
                $summary = [
                    'tg' => "Маҷмӯи санҷишии такроршаванда {$sequence}",
                    'ru' => "Воспроизводимый benchmark-набор {$sequence}",
                    'en' => "Deterministic benchmark dataset {$sequence}",
                ];
                $body = $index % 100 === 0
                    ? ['tg' => $longBody, 'ru' => $longBody, 'en' => $longBody]
                    : $summary;

                $rows[] = [
                    'title' => $this->encode($title),
                    'summary' => $this->encode($summary),
                    'body' => $this->encode($body),
                    'slug' => "benchmark-news-{$sequence}",
                    'status' => ContentStatus::Published->value,
                    'cover_alt' => $title['ru'],
                    'is_pinned' => false,
                    'show_on_home' => $index <= 12,
                    'views_count' => ($index * 7919) % 250_000,
                    'seo' => $this->encode([
                        'tg' => ['title' => $title['tg'], 'description' => $summary['tg']],
                        'ru' => ['title' => $title['ru'], 'description' => $summary['ru']],
                        'en' => ['title' => $title['en'], 'description' => $summary['en']],
                    ]),
                    'published_at' => $now->copy()->subMinutes($index),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            DB::table('news')->upsert(
                $rows,
                ['slug'],
                [
                    'title',
                    'summary',
                    'body',
                    'status',
                    'cover_alt',
                    'is_pinned',
                    'show_on_home',
                    'views_count',
                    'seo',
                    'published_at',
                    'updated_at',
                ],
            );
        }
    }

    private function seedMedia(int $count, int $totalBytes): int
    {
        $existingTitles = DB::table('media_assets')
            ->where('title', 'like', 'Benchmark media %')
            ->pluck('title')
            ->all();
        $existing = array_fill_keys($existingTitles, true);
        $now = now();

        foreach (array_chunk(range(1, $count), 500) as $indexes) {
            $assets = [];

            foreach ($indexes as $index) {
                $title = $this->mediaTitle($index);

                if (isset($existing[$title])) {
                    continue;
                }

                $assets[] = [
                    'title' => $title,
                    'alt' => "Benchmark image {$index}",
                    'caption' => 'Production-like performance fixture',
                    'is_decorative' => false,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            if ($assets !== []) {
                DB::table('media_assets')->insert($assets);
            }
        }

        $assets = DB::table('media_assets')
            ->whereIn('title', array_map($this->mediaTitle(...), range(1, $count)))
            ->orderBy('id')
            ->get(['id', 'title']);
        $existingMediaIds = DB::table('media')
            ->where('model_type', MediaAsset::class)
            ->whereIn('model_id', $assets->pluck('id'))
            ->pluck('model_id')
            ->mapWithKeys(fn (mixed $id): array => [(int) $id => true]);
        $sizes = $this->mediaSizes($count, $totalBytes);

        $assets->chunk(500)->each(function (Collection $chunk) use ($existingMediaIds, $now, $sizes): void {
            $rows = [];

            foreach ($chunk as $asset) {
                $assetId = (int) $asset->id;

                if ($existingMediaIds->has($assetId)) {
                    continue;
                }

                $index = (int) Str::afterLast((string) $asset->title, ' ');
                $fileName = "benchmark-{$index}.svg";
                $rows[] = [
                    'model_type' => MediaAsset::class,
                    'model_id' => $assetId,
                    'collection_name' => 'asset',
                    'name' => "benchmark-{$index}",
                    'file_name' => $fileName,
                    'mime_type' => 'image/svg+xml',
                    'disk' => 'benchmark',
                    'conversions_disk' => 'benchmark',
                    'size' => $sizes[$index],
                    'manipulations' => '[]',
                    'custom_properties' => $this->encode([
                        'width' => 8000,
                        'height' => 8000,
                        'benchmark' => true,
                    ]),
                    'generated_conversions' => '[]',
                    'responsive_images' => '[]',
                    'order_column' => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            if ($rows !== []) {
                DB::table('media')->insert($rows);
            }
        });

        $mediaRows = DB::table('media')
            ->where('model_type', MediaAsset::class)
            ->whereIn('model_id', $assets->pluck('id'))
            ->get(['id', 'file_name', 'size']);

        foreach ($mediaRows as $media) {
            $path = "{$media->id}/{$media->file_name}";

            if (
                Storage::disk('benchmark')->exists($path)
                && Storage::disk('benchmark')->size($path) === (int) $media->size
            ) {
                continue;
            }

            Storage::disk('benchmark')->put(
                $path,
                $this->benchmarkSvg((int) $media->size),
            );
        }

        return $mediaRows->sum(fn (object $media): int => (int) $media->size);
    }

    /**
     * @return array<int, int>
     */
    private function mediaSizes(int $count, int $totalBytes): array
    {
        $largeImageBytes = $this->largeImageBytes($count, $totalBytes);
        $remainingBytes = $totalBytes - $largeImageBytes;
        $remainingCount = $count - 1;
        $baseSize = $remainingCount > 0 ? intdiv($remainingBytes, $remainingCount) : 0;
        $remainder = $remainingCount > 0 ? $remainingBytes % $remainingCount : 0;
        $sizes = [1 => $largeImageBytes];

        if ($count > 1) {
            foreach (range(2, $count) as $index) {
                $sizes[$index] = $baseSize + ($index - 2 < $remainder ? 1 : 0);
            }
        }

        return $sizes;
    }

    private function largeImageBytes(int $count, int $totalBytes): int
    {
        $minimumRemaining = max(0, $count - 1) * 512;

        return min(10 * 1024 * 1024, $totalBytes - $minimumRemaining);
    }

    private function benchmarkSvg(int $targetBytes): string
    {
        $prefix = '<svg xmlns="http://www.w3.org/2000/svg" width="8000" height="8000"><!--';
        $suffix = '--><rect width="8000" height="8000" fill="#416180"/></svg>';
        $padding = max(0, $targetBytes - strlen($prefix) - strlen($suffix));

        return $prefix.str_repeat('x', $padding).$suffix;
    }

    private function mediaTitle(int $index): string
    {
        return 'Benchmark media '.str_pad((string) $index, 6, '0', STR_PAD_LEFT);
    }

    /**
     * @param  array<string, mixed>  $value
     *
     * @throws JsonException
     */
    private function encode(array $value, bool $pretty = false): string
    {
        return json_encode(
            $value,
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | ($pretty ? JSON_PRETTY_PRINT : 0),
        );
    }
}
