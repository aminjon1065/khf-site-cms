<?php

namespace App\Http\Requests\Instruction;

use App\Enums\HazardType;
use App\Models\Instruction;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InstructionRequest extends FormRequest
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
        /** @var Instruction|null $instruction */
        $instruction = $this->route('instruction');

        return [
            'name' => ['array'],
            'name.ru' => ['required', 'string', 'max:255'],
            'name.tg' => ['nullable', 'string', 'max:255'],
            'name.en' => ['nullable', 'string', 'max:255'],

            'summary' => ['array'],
            'summary.ru' => ['nullable', 'string', 'max:1000'],
            'summary.tg' => ['nullable', 'string', 'max:1000'],
            'summary.en' => ['nullable', 'string', 'max:1000'],

            'body' => ['array'],
            'body.ru' => ['nullable', 'string', 'max:20000'],
            'body.tg' => ['nullable', 'string', 'max:20000'],
            'body.en' => ['nullable', 'string', 'max:20000'],

            'hazard_type' => ['nullable', Rule::enum(HazardType::class)],
            'is_priority' => ['boolean'],
            'sort' => ['nullable', 'integer', 'min:0', 'max:9999'],

            'slug' => [
                'nullable', 'string', 'max:255', 'alpha_dash',
                Rule::unique('instructions', 'slug')->ignore($instruction?->id),
            ],

            // sections: { before|during|after|prohibited: { ru|tg|en: string[] } }
            'sections' => ['nullable', 'array'],
            'sections.*' => ['array'],
            'sections.*.*' => ['array'],
            'sections.*.*.*' => ['nullable', 'string', 'max:1000'],

            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'image_media_id' => ['nullable', 'integer', 'exists:media,id'],
            'image_remove' => ['boolean'],

            'publish_mode' => ['nullable', 'in:now,schedule,review'],
            'action' => ['nullable', 'in:draft,submit'],
            'stay' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.ru.required' => 'Укажите название инструкции на русском языке.',
            'slug.unique' => 'Такой адрес (slug) уже используется другой инструкцией.',
            'slug.alpha_dash' => 'Адрес может содержать только латинские буквы, цифры и дефисы.',
            'hazard_type.enum' => 'Выбран несуществующий тип события.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name.ru' => 'название (рус.)',
            'slug' => 'адрес',
            'hazard_type' => 'тип события',
        ];
    }
}
