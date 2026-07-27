<?php

namespace App\Http\Requests\StructureUnit;

use Illuminate\Foundation\Http\FormRequest;

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
        return [
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
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
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
            'num' => 'номер',
            'name.ru' => 'название (рус.)',
            'desc.ru' => 'описание (рус.)',
        ];
    }
}
