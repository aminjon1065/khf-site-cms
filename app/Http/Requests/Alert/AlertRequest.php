<?php

namespace App\Http\Requests\Alert;

use App\Enums\HazardType;
use App\Enums\Severity;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AlertRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'internal_title' => ['required', 'string', 'max:255'],
            'hazard_type' => ['required', Rule::enum(HazardType::class)],
            'severity' => ['required', Rule::enum(Severity::class)],
            'source' => ['nullable', 'string', 'max:255'],
            'risk_category' => ['nullable', 'string', 'max:100'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'scheduled_at' => ['nullable', 'date'],

            'territory_type' => ['required', 'in:country,regions'],
            'territory_note' => ['nullable', 'string', 'max:2000'],
            'regions' => ['array'],
            'regions.*' => ['integer', 'exists:regions,id'],
            'districts' => ['array'],
            'districts.*' => ['integer', 'exists:districts,id'],
            'related_instructions' => ['array'],
            'related_instructions.*' => ['integer', 'exists:instructions,id'],

            'title' => ['array'],
            'title.ru' => ['nullable', 'string', 'max:255'],
            'title.tg' => ['nullable', 'string', 'max:255'],
            'title.en' => ['nullable', 'string', 'max:255'],
            'summary' => ['array'],
            'body' => ['array'],
            'instructions' => ['array'],
            'contacts' => ['array'],

            'channels' => ['array'],
            'channels.*' => ['in:site,sos_app,rss,sms'],

            'approver_id' => ['nullable', 'integer', 'exists:users,id'],
            'publish_mode' => ['nullable', 'in:now,schedule,review'],
            'action' => ['nullable', 'in:draft,submit'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'internal_title.required' => 'Укажите внутреннее название предупреждения.',
            'hazard_type.required' => 'Выберите тип события.',
            'severity.required' => 'Выберите уровень опасности.',
            'ends_at.after_or_equal' => 'Дата завершения не может быть раньше начала действия.',
            'territory_type.required' => 'Укажите затронутую территорию.',
            'regions.*.exists' => 'Выбран несуществующий регион.',
            'approver_id.exists' => 'Выбран несуществующий согласующий.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'internal_title' => 'внутреннее название',
            'hazard_type' => 'тип события',
            'severity' => 'уровень опасности',
            'starts_at' => 'начало действия',
            'ends_at' => 'завершение',
        ];
    }
}
