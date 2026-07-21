<?php

namespace App\Http\Requests\Alert;

use App\Enums\HazardType;
use App\Enums\RoleName;
use App\Enums\Severity;
use App\Models\Alert;
use App\Models\District;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class AlertRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $alert = $this->route('alert');

        if ($user === null) {
            return false;
        }

        return $alert instanceof Alert
            ? $user->can('update', $alert)
            : $user->can('create', Alert::class);
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
            'scheduled_at' => [
                Rule::requiredIf(fn (): bool => $this->input('action') === 'submit' && $this->input('publish_mode') === 'schedule'),
                'nullable',
                'date',
                'after:now',
            ],

            'territory_type' => ['required', 'in:country,regions'],
            'territory_note' => ['nullable', 'string', 'max:2000'],
            'regions' => [Rule::requiredIf(fn (): bool => $this->input('territory_type') === 'regions'), 'array', 'min:1'],
            'regions.*' => ['integer', 'distinct', 'exists:regions,id'],
            'districts' => ['array'],
            'districts.*' => ['integer', 'distinct', 'exists:districts,id'],
            'related_instructions' => ['array'],
            'related_instructions.*' => ['integer', 'distinct', 'exists:instructions,id'],

            'title' => ['array'],
            'title.ru' => ['nullable', 'string', 'max:255'],
            'title.tg' => ['nullable', 'string', 'max:255'],
            'title.en' => ['nullable', 'string', 'max:255'],
            'summary' => ['array'],
            'summary.ru' => ['nullable', 'string', 'max:1000'],
            'summary.tg' => ['nullable', 'string', 'max:1000'],
            'summary.en' => ['nullable', 'string', 'max:1000'],
            'body' => ['array'],
            'body.ru' => ['nullable', 'string', 'max:100000'],
            'body.tg' => ['nullable', 'string', 'max:100000'],
            'body.en' => ['nullable', 'string', 'max:100000'],
            'instructions' => ['array'],
            'instructions.ru' => ['nullable', 'string', 'max:20000'],
            'instructions.tg' => ['nullable', 'string', 'max:20000'],
            'instructions.en' => ['nullable', 'string', 'max:20000'],
            'contacts' => ['array'],
            'contacts.ru' => ['nullable', 'string', 'max:10000'],
            'contacts.tg' => ['nullable', 'string', 'max:10000'],
            'contacts.en' => ['nullable', 'string', 'max:10000'],

            'channels' => ['array'],
            'channels.*' => ['in:site,sos_app,rss,sms'],

            'approver_id' => ['nullable', 'integer', 'exists:users,id'],
            'publish_mode' => ['nullable', 'in:now,schedule,review'],
            'action' => ['nullable', 'in:draft,submit'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $territoryType = $this->string('territory_type')->toString();
                $regionIds = $this->integerList('regions');
                $districtIds = $this->integerList('districts');

                if ($territoryType === 'country' && ($regionIds !== [] || $districtIds !== [])) {
                    $validator->errors()->add('regions', 'Для общенационального предупреждения регионы и районы не выбираются.');
                }

                if ($districtIds !== [] && District::query()->whereKey($districtIds)->whereNotIn('region_id', $regionIds)->exists()) {
                    $validator->errors()->add('districts', 'Все выбранные районы должны относиться к выбранным регионам.');
                }

                $user = $this->user();
                if (! $user?->hasRole(RoleName::RegionalEditor->value)) {
                    return;
                }

                if ($territoryType !== 'regions' || $user->region_id === null || $regionIds !== [$user->region_id]) {
                    $validator->errors()->add('regions', 'Региональный редактор может публиковать материалы только для своего региона.');
                }
            },
        ];
    }

    /**
     * @return list<int>
     */
    private function integerList(string $key): array
    {
        $value = $this->input($key, []);

        if (! is_array($value)) {
            return [];
        }

        return array_values(array_map(fn (mixed $id): int => (int) $id, $value));
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
            'scheduled_at.required' => 'Укажите дату плановой публикации.',
            'scheduled_at.after' => 'Дата плановой публикации должна быть в будущем.',
            'territory_type.required' => 'Укажите затронутую территорию.',
            'regions.required' => 'Выберите хотя бы один регион.',
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
