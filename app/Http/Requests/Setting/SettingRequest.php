<?php

namespace App\Http\Requests\Setting;

use Illuminate\Foundation\Http\FormRequest;

class SettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Authorization is enforced in the controller.
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'settings' => ['array'],
            'settings.*' => ['array'],
            'settings.*.*' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
