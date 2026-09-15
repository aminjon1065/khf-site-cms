<?php

namespace App\Concerns;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

trait PasswordValidationRules
{
    /**
     * Get the validation rules used to validate passwords.
     *
     * @return array<int, Password|ValidationRule|array<mixed>|string>
     */
    protected function passwordRules(): array
    {
        return ['required', 'string', Password::default(), 'confirmed'];
    }

    /**
     * Get the validation rules used to validate the current password.
     *
     * @return array<int, Password|ValidationRule|array<mixed>|string>
     */
    protected function currentPasswordRules(): array
    {
        return ['required', 'string', 'current_password'];
    }

    /**
     * Human-readable password requirements for UI hints. Mirrors
     * passwordRules(); toPasswordRulesString() leaks technical
     * "minlength: 8;" markup and must not be rendered to users.
     */
    protected function passwordRulesHint(): string
    {
        $rule = Password::default();

        if (! $rule instanceof Password) {
            return 'Не менее 8 символов.';
        }

        $min = (new \ReflectionProperty($rule, 'min'))->getValue($rule) ?? 8;
        $parts = ["не менее {$min} символов"];

        // Mirrors the production rule composition in AppServiceProvider.
        if (app()->isProduction()) {
            $parts[] = 'заглавные и строчные буквы, цифры и специальные символы';
        }

        return Str::ucfirst(implode(', ', $parts)).'.';
    }
}
