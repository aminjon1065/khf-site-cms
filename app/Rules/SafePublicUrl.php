<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class SafePublicUrl implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }

        if (! is_string($value)) {
            $fail('Ссылка должна быть строкой.');

            return;
        }

        if (preg_match('#^/(?!/)#', $value) === 1) {
            return;
        }

        if (preg_match('#^(?:https://|mailto:|tel:)#i', $value) === 1) {
            return;
        }

        $fail('Разрешены внутренние пути /..., HTTPS, mailto: и tel:.');
    }
}
