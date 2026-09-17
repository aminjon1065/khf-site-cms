<?php

namespace App\Http\Requests\StructureUnit;

use App\Models\StructureUnit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StructureUnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Authorization is enforced in the controller via policies.
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var StructureUnit|null $unit */
        $unit = $this->route('structureUnit');

        return [
            'parent_id' => [
                'nullable', 'integer',
                Rule::exists('structure_units', 'id')->whereNot('id', $unit?->id),
            ],
            'num' => ['required', 'string', 'max:10'],

            'name' => ['array'],
            'name.ru' => ['required', 'string', 'max:255'],
            'name.tg' => ['nullable', 'string', 'max:255'],
            'name.en' => ['nullable', 'string', 'max:255'],

            'desc' => ['array'],
            'desc.ru' => ['required', 'string', 'max:1000'],
            'desc.tg' => ['nullable', 'string', 'max:1000'],
            'desc.en' => ['nullable', 'string', 'max:1000'],

            'sort' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ];
    }

    /**
     * A unit cannot be placed under one of its own subunits: walking up from
     * the chosen parent must never reach the unit itself.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            /** @var StructureUnit|null $unit */
            $unit = $this->route('structureUnit');
            $parentId = $this->integer('parent_id');

            if ($unit === null || $parentId === 0) {
                return;
            }

            $visited = [];
            $parent = StructureUnit::query()->find($parentId);

            while ($parent !== null) {
                if ($parent->is($unit) || in_array($parent->id, $visited, true)) {
                    $validator->errors()->add('parent_id', 'Подразделение нельзя подчинить его собственному вложенному подразделению.');

                    return;
                }

                $visited[] = $parent->id;
                $parent = $parent->parent_id !== null
                    ? StructureUnit::query()->find($parent->parent_id)
                    : null;
            }
        }];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'parent_id.exists' => 'Выбранное вышестоящее подразделение не найдено.',
            'num.required' => 'Укажите номер подразделения.',
            'name.ru.required' => 'Укажите название на русском языке.',
            'desc.ru.required' => 'Укажите описание на русском языке.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'parent_id' => 'вышестоящее подразделение',
            'num' => 'номер',
            'name.ru' => 'название (рус.)',
            'desc.ru' => 'описание (рус.)',
        ];
    }
}
