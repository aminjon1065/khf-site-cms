<?php

namespace App\Services;

use Closure;
use Illuminate\Support\Facades\Cache;

final class PublicReadModelCache
{
    /**
     * @var array<string, array{0: int, 1: int}>
     */
    private const WINDOWS = [
        self::ALERTS => [10, 30],
        self::CATEGORIES => [600, 3600],
        self::HOME => [60, 300],
        self::MENU => [600, 3600],
        self::REGIONS => [300, 1800],
        self::SETTINGS => [600, 3600],
        self::SLUGS => [300, 1800],
    ];

    public const ALERTS = 'alerts';

    public const CATEGORIES = 'categories';

    public const HOME = 'home';

    public const MENU = 'menu';

    public const REGIONS = 'regions';

    public const SETTINGS = 'settings';

    public const SLUGS = 'slugs';

    public function remember(string $namespace, string $variant, Closure $resolver): mixed
    {
        $version = $this->version($namespace);
        $variantHash = hash('sha256', $variant);
        $key = "public-read-model:{$namespace}:v{$version}:{$variantHash}";

        return Cache::flexible(
            $key,
            self::WINDOWS[$namespace] ?? [60, 300],
            $resolver,
        );
    }

    public function invalidate(string ...$namespaces): void
    {
        foreach (array_unique($namespaces) as $namespace) {
            $key = $this->versionKey($namespace);
            $cache = Cache::memo();

            $cache->add($key, 1);
            $cache->increment($key);
        }
    }

    private function version(string $namespace): int
    {
        $key = $this->versionKey($namespace);

        return (int) Cache::memo()->rememberForever($key, fn (): int => 1);
    }

    private function versionKey(string $namespace): string
    {
        return "public-read-model:{$namespace}:version";
    }
}
