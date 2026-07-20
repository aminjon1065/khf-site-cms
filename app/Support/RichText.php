<?php

namespace App\Support;

use Mews\Purifier\Facades\Purifier;

/**
 * Server-side sanitisation for rich-text (Tiptap) content bodies shared by
 * news, projects and instructions.
 */
class RichText
{
    /**
     * Sanitise a per-locale rich-text map against the `news` HTMLPurifier
     * profile, dropping locales that are empty after cleaning (the editor emits
     * `<p></p>` for a blank field).
     *
     * @return array<string, string>
     */
    public static function sanitizeTranslations(mixed $input): array
    {
        $clean = [];

        foreach ((array) $input as $locale => $html) {
            if (! is_string($html) || trim($html) === '') {
                continue;
            }

            $sanitized = trim(Purifier::clean($html, 'news'));
            if ($sanitized !== '') {
                $clean[$locale] = $sanitized;
            }
        }

        return $clean;
    }
}
