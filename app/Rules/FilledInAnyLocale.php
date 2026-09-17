<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

/**
 * A translatable field must be filled in at least one content language. A
 * material may exist in Tajik, Russian or English alone — the public site
 * lists it only on the language versions where its title exists.
 */
class FilledInAnyLocale implements ValidationRule
{
    /**
     * Indicates whether the rule should be implicit.
     *
     * @var bool
     */
    public $implicit = true;

    /**
     * @var list<string>
     */
    private const LOCALES = ['tg', 'ru', 'en'];

    public function __construct(private readonly string $message) {}

    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (is_array($value)) {
            foreach (self::LOCALES as $locale) {
                if (is_string($value[$locale] ?? null) && trim($value[$locale]) !== '') {
                    return;
                }
            }
        }

        $fail($this->message);
    }
}
