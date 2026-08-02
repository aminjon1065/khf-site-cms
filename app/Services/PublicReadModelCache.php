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
        self::LEADERSHIP => [600, 3600],
        self::MENU => [600, 3600],
        self::REGIONS => [300, 1800],
        self::SETTINGS => [600, 3600],
        self::SLUGS => [300, 1800],
        self::STRUCTURE => [600, 3600],
    ];

    public const ALERTS = 'alerts';

    public const CATEGORIES = 'categories';

    public const HOME = 'home';

    public const LEADERSHIP = 'leadership';

    public const MENU = 'menu';

    public const REGIONS = 'regions';

    public const SETTINGS = 'settings';

    public const SLUGS = 'slugs';

    public const STRUCTURE = 'structure';

    /**
     * Lookups served from cache and lookups that had to build the value, for
     * the current request only. A cache whose hit rate nobody can see is a
     * cache nobody notices going cold — this is what turns it into the
     * `X-Cache` header and the API cache metric.
     *
     * @var array{hits: int, misses: int}
     */
    private array $outcomes = ['hits' => 0, 'misses' => 0];

    public function remember(string $namespace, string $variant, Closure $resolver): mixed
    {
        $version = $this->version($namespace);
        $variantHash = hash('sha256', $variant);
        $key = "public-read-model:{$namespace}:v{$version}:{$variantHash}";
        $built = false;

        $value = Cache::flexible(
            $key,
            self::WINDOWS[$namespace] ?? [60, 300],
            function () use ($resolver, &$built): mixed {
                $built = true;

                return $resolver();
            },
        );

        $this->outcomes[$built ? 'misses' : 'hits']++;

        return $value;
    }

    /**
     * Обнуляет счётчики на старте запроса. Контейнер отдаёт этот сервис
     * `scoped`, то есть под FPM он и так живёт один запрос; явный сброс нужен
     * для сред, где контейнер переживает запрос (Octane, тесты), — иначе
     * статистика прошлого запроса протекала бы в следующий.
     */
    public function startRequest(): void
    {
        $this->outcomes = ['hits' => 0, 'misses' => 0];
    }

    /**
     * `hit` — всё, что понадобилось запросу, пришло из кэша; `miss` — ничего;
     * `partial` — часть. `null` означает, что запрос вообще не обращался к
     * read-model-кэшу, и это не то же самое, что промах.
     */
    public function outcome(): ?string
    {
        return match (true) {
            $this->outcomes['hits'] === 0 && $this->outcomes['misses'] === 0 => null,
            $this->outcomes['misses'] === 0 => 'hit',
            $this->outcomes['hits'] === 0 => 'miss',
            default => 'partial',
        };
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
