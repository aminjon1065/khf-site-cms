<?php

namespace App\Support;

use Illuminate\Support\Str;

final class WebVitalThresholds
{
    /**
     * @var list<string>
     */
    public const METRICS = ['LCP', 'INP', 'CLS'];

    /**
     * @var array<string, array{good: float, poor: float}>
     */
    private const THRESHOLDS = [
        'LCP' => ['good' => 2500.0, 'poor' => 4000.0],
        'INP' => ['good' => 200.0, 'poor' => 500.0],
        'CLS' => ['good' => 0.1, 'poor' => 0.25],
    ];

    public static function rating(string $metric, float $value): string
    {
        $threshold = self::THRESHOLDS[$metric] ?? null;

        if ($threshold === null) {
            return 'unknown';
        }

        if ($value <= $threshold['good']) {
            return 'good';
        }

        return $value <= $threshold['poor'] ? 'needs-improvement' : 'poor';
    }

    public static function normalizeRoute(string $path): string
    {
        $path = (string) parse_url($path, PHP_URL_PATH);
        $path = '/'.Str::of($path)->trim('/')->replaceMatches('/\/+/', '/');

        if (! preg_match('#^/(ru|tj|en)(?:/|$)#', $path)) {
            return '/other';
        }

        $normalized = preg_replace(
            '#^/(ru|tj|en)/(news|alerts|guides|projects|pages|announcements)/[^/]+/?$#',
            '/$1/$2/[slug]',
            $path,
        ) ?? $path;
        $normalized = preg_replace(
            '#/(?:(?:[0-9]+)|(?:[0-9a-f]{8}-[0-9a-f-]{27,}))(?:/|$)#i',
            '/[id]/',
            $normalized,
        ) ?? $normalized;

        return Str::limit(rtrim($normalized, '/') ?: '/', 160, '');
    }
}
