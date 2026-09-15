<?php

namespace App\Support;

/**
 * Stored rich-text content must reference media by path ("/storage/…"),
 * not by absolute URL: hosts and ports change between environments and
 * a baked-in origin silently breaks every image after a move.
 */
final class MediaUrl
{
    /**
     * Strip the scheme and host from a media URL, keeping the path and
     * query string. URLs that are already root-relative pass through.
     */
    public static function toRelative(string $url): string
    {
        $withoutFragment = preg_replace('/#.*$/', '', $url) ?? $url;

        $parts = parse_url($withoutFragment);

        if (! is_array($parts) || ! isset($parts['host'])) {
            return $url;
        }

        $relative = ($parts['path'] ?? '/');

        if (isset($parts['query'])) {
            $relative .= '?'.$parts['query'];
        }

        return $relative;
    }
}
