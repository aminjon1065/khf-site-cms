<?php

namespace App\Http\Requests\Setting;

use App\Rules\SafePublicUrl;
use App\Support\SituationFreshness;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'settings.social.*' => ['nullable', 'string', 'max:255', new SafePublicUrl],
            'settings.situation.stale_after_minutes' => ['nullable', Rule::in(SituationFreshness::values())],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'settings.situation.stale_after_minutes.in' => 'Выберите срок из списка.',
        ];
    }
}
