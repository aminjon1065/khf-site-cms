<?php

namespace App\Support;

/**
 * Compares raw column values by meaning, not by bytes: the same translations
 * stored as JSON with a different key order or with empty languages dropped
 * are not a change, nor is `1` versus `true`. Saving a form without edits
 * must not look like an edit — to approvers (PendingChangeService) or to
 * search engines (dateModified, TracksContentEdits).
 */
final class StoredValue
{
    public static function same(mixed $before, mixed $after): bool
    {
        $decodedBefore = is_string($before) ? json_decode($before, true) : $before;
        $decodedAfter = is_string($after) ? json_decode($after, true) : $after;

        if (is_array($decodedBefore) || is_array($decodedAfter)) {
            return self::normalized($decodedBefore) === self::normalized($decodedAfter);
        }

        return (string) (is_bool($before) ? (int) $before : $before) === (string) (is_bool($after) ? (int) $after : $after);
    }

    private static function normalized(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value === '' ? null : $value;
        }

        $clean = [];

        foreach ($value as $key => $item) {
            $item = self::normalized($item);

            if ($item !== null && $item !== []) {
                $clean[$key] = $item;
            }
        }

        if (! array_is_list($clean)) {
            ksort($clean);
        }

        return $clean;
    }
}
