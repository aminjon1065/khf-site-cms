<?php

namespace App\Support;

/**
 * Rewrites absolute media origins inside stored rich-text HTML into
 * root-relative paths. Attribute boundaries (quote, whitespace, comma)
 * anchor the match so srcset lists with space/comma-separated URLs are
 * rewritten correctly; only /storage/ media origins are touched —
 * external images (CDNs, YouTube thumbnails) stay absolute.
 */
final class ContentMediaUrls
{
    public static function relativizeHtml(string $html): string
    {
        if ($html === '' || ! str_contains($html, '://')) {
            return $html;
        }

        return (string) preg_replace(
            '#(?<=["\x27\s,])https?://[^/"\x27\s,]+/storage/#',
            '/storage/',
            $html,
        );
    }

    /**
     * Reverse direction for consumers that cannot resolve root-relative
     * URLs (external API clients): prefix /storage/ paths with the
     * configured media origin.
     */
    public static function absolutizeHtml(string $html, string $baseUrl): string
    {
        if ($html === '' || $baseUrl === '') {
            return $html;
        }

        $base = rtrim($baseUrl, '/');

        return (string) preg_replace(
            '#(?<=["\x27\s,])(/storage/)#',
            $base.'$1',
            $html,
        );
    }
}
