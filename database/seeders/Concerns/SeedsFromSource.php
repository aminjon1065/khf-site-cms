<?php

namespace Database\Seeders\Concerns;

use App\Support\PublicLocale;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use JsonException;
use Spatie\MediaLibrary\HasMedia;
use Throwable;

/**
 * Shared plumbing for the seeders that replay `khf:scrape-source` fixtures:
 * reading the committed JSON and attaching the images it points at.
 *
 * Text never needs the network. Images are downloaded once into
 * `storage/app/seed-source-media` and reused afterwards, and any failure is
 * non-fatal so a re-seed on a machine without internet still succeeds — the
 * material is simply seeded without a cover.
 */
trait SeedsFromSource
{
    /** @var array<string, bool> */
    private array $unreachableHosts = [];

    /**
     * Reads one fixture written by `php artisan khf:scrape-source`.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function sourceItems(string $file): array
    {
        $path = $this->sourceFixturePath()."/{$file}";

        if (! is_file($path)) {
            return [];
        }

        try {
            /** @var array<string, mixed> $payload */
            $payload = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return [];
        }

        $items = $payload['items'] ?? [];

        if (! is_array($items)) {
            return [];
        }

        $records = [];

        foreach ($items as $item) {
            if (is_array($item)) {
                $records[] = $item;
            }
        }

        return $records;
    }

    /**
     * Directory the `khf:scrape-source` fixtures are read from.
     */
    protected function sourceFixturePath(): string
    {
        return (string) config('seeding.source.path', database_path('seeders/data/source'));
    }

    /**
     * Joins the Russian and Tajik fixtures into one list of records.
     *
     * kchs.tj and khf.tj are separate sites, not translations of each other, so
     * most items exist in a single language — which is what the public locale
     * contract expects ({@see PublicLocale}). Where both sites
     * published the same material, the shared cover image identifies the pair
     * and the two texts belong on one CMS record.
     *
     * @return array<int, array{ru: array<string, mixed>|null, tg: array<string, mixed>|null}>
     */
    protected function pairSourceItems(string $russianFile, string $tajikFile, int $windowDays = 21): array
    {
        $russian = $this->sourceItems($russianFile);
        $tajik = $this->sourceItems($tajikFile);

        $index = [];

        foreach ($tajik as $key => $item) {
            $fingerprint = $this->coverFingerprint(is_string($item['cover'] ?? null) ? $item['cover'] : null);

            if ($fingerprint !== null) {
                $index[$fingerprint][] = $key;
            }
        }

        $pairs = [];
        $taken = [];

        foreach ($russian as $item) {
            $match = $this->matchingItem($item, $tajik, $index, $taken, $windowDays);

            if ($match !== null) {
                $taken[$match] = true;
                $pairs[] = ['ru' => $item, 'tg' => $tajik[$match]];

                continue;
            }

            $pairs[] = ['ru' => $item, 'tg' => null];
        }

        foreach ($tajik as $key => $item) {
            if (! isset($taken[$key])) {
                $pairs[] = ['ru' => null, 'tg' => $item];
            }
        }

        return $pairs;
    }

    /**
     * @param  array<string, mixed>  $russian
     * @param  array<int, array<string, mixed>>  $tajik
     * @param  array<string, array<int, int>>  $index
     * @param  array<int, bool>  $taken
     */
    private function matchingItem(array $russian, array $tajik, array $index, array $taken, int $windowDays): ?int
    {
        $fingerprint = $this->coverFingerprint(is_string($russian['cover'] ?? null) ? $russian['cover'] : null);

        if ($fingerprint === null || ! isset($index[$fingerprint])) {
            return null;
        }

        $publishedAt = Carbon::parse((string) $russian['published_at']);

        foreach ($index[$fingerprint] as $key) {
            if (isset($taken[$key])) {
                continue;
            }

            $candidate = Carbon::parse((string) $tajik[$key]['published_at']);

            if ($publishedAt->diffInDays($candidate, absolute: true) <= $windowDays) {
                return $key;
            }
        }

        return null;
    }

    /**
     * Reduces a cover URL to the identity the two sites share: the same photo
     * is re-uploaded under the same base name, give or take Drupal's size,
     * `_web` and duplicate suffixes. Names too short to be distinctive are
     * rejected, so unrelated files never pair up.
     */
    protected function coverFingerprint(?string $url): ?string
    {
        if ($url === null || $url === '') {
            return null;
        }

        $name = Str::lower(urldecode(basename((string) parse_url($url, PHP_URL_PATH))));
        $name = (string) preg_replace('~\.[a-z0-9]+$~', '', $name);
        $name = (string) preg_replace('~[_-]?(web|copy|small|\d{2,4}x\d{2,4})$~', '', $name);
        $name = (string) preg_replace('~[_-]\d$~', '', $name);
        $name = (string) preg_replace('~[^a-z0-9]+~', '', $name);

        return strlen($name) >= 6 ? $name : null;
    }

    /**
     * Adds a source image to a media collection, leaving an existing one alone.
     * Returns false when the image could not be fetched.
     */
    protected function attachSourceImage(HasMedia $model, ?string $url, string $collection): bool
    {
        if ($url === null || $url === '') {
            return false;
        }

        if ($model->hasMedia($collection)) {
            return true;
        }

        $path = $this->sourceFilePath($url);

        if ($path === null) {
            return false;
        }

        try {
            $model->addMedia($path)
                ->preservingOriginal()
                ->usingFileName($this->sourceFileName($url))
                ->toMediaCollection($collection);
        } catch (Throwable) {
            return false;
        }

        return true;
    }

    /**
     * Makes sure a source file is on disk and returns its local path, or null
     * when it could not be fetched. The cache is preserved: media-library adds
     * copy from it rather than moving it, so a re-seed never refetches.
     *
     * Switching source media off returns null outright — the cache is not
     * consulted either, so a run is hermetic whether or not the developer's
     * machine happens to have downloaded the file before. That is what keeps
     * tests and CI reproducible.
     */
    protected function sourceFilePath(string $url): ?string
    {
        if (! config('seeding.source.media')) {
            return null;
        }

        $cachePath = $this->sourceCachePath($url);

        if (is_file($cachePath)) {
            return $cachePath;
        }

        $host = (string) parse_url($url, PHP_URL_HOST);

        if (isset($this->unreachableHosts[$host])) {
            return null;
        }

        $caBundle = (string) config('seeding.source.ca_bundle', '');

        try {
            $response = Http::timeout((int) config('seeding.source.timeout', 20))
                ->withHeaders(['User-Agent' => 'khf-site-cms seeder (+https://khf.tj)'])
                ->when(
                    $caBundle !== '',
                    fn (PendingRequest $request): PendingRequest => $request->withOptions(['verify' => $caBundle]),
                )
                ->get($url);
        } catch (Throwable) {
            $this->unreachableHosts[$host] = true;

            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        if (! is_dir(dirname($cachePath))) {
            mkdir(dirname($cachePath), recursive: true);
        }

        file_put_contents($cachePath, $response->body());

        return $cachePath;
    }

    /**
     * A stable, collision-free cache path: the URL hash keeps two files of the
     * same name from different directories apart.
     */
    protected function sourceCachePath(string $url): string
    {
        $directory = (string) config('seeding.source.cache_path', storage_path('app/seed-source-media'));

        return $directory.'/'.substr(sha1($url), 0, 16).'-'.$this->sourceFileName($url);
    }

    protected function sourceFileName(string $url): string
    {
        $name = urldecode(basename((string) parse_url($url, PHP_URL_PATH)));
        $extension = Str::lower(pathinfo($name, PATHINFO_EXTENSION));
        $base = Str::slug(pathinfo($name, PATHINFO_FILENAME)) ?: 'file';

        return $extension === '' ? $base : "{$base}.{$extension}";
    }
}
