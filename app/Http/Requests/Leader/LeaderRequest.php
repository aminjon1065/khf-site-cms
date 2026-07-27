<?php

namespace App\Http\Requests\Leader;

use Illuminate\Foundation\Http\FormRequest;

class LeaderRequest extends FormRequest
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
            'role' => ['array'],
            'role.ru' => ['required', 'string', 'max:255'],
            'role.tg' => ['nullable', 'string', 'max:255'],
            'role.en' => ['nullable', 'string', 'max:255'],

            'name' => ['array'],
            'name.ru' => ['required', 'string', 'max:255'],
            'name.tg' => ['nullable', 'string', 'max:255'],
            'name.en' => ['nullable', 'string', 'max:255'],

            'meta' => ['nullable', 'array'],
            'meta.ru' => ['nullable', 'string', 'max:255'],
            'meta.tg' => ['nullable', 'string', 'max:255'],
            'meta.en' => ['nullable', 'string', 'max:255'],

            'bio' => ['nullable', 'array'],
            'bio.ru' => ['nullable', 'string', 'max:2000'],
            'bio.tg' => ['nullable', 'string', 'max:2000'],
            'bio.en' => ['nullable', 'string', 'max:2000'],

            'is_chairman' => ['nullable', 'boolean'],
            'sort' => ['nullable', 'integer', 'min:0', 'max:9999'],

            'photo' => ['nullable', 'image', 'max:5120'],
            'photo_remove' => ['nullable', 'boolean'],
            'photo_media_id' => ['nullable', 'integer'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'role.ru.required' => 'Укажите должность на русском языке.',
            'name.ru.required' => 'Укажите ФИО на русском языке.',
            'photo.image' => 'Файл должен быть изображением.',
            'photo.max' => 'Изображение не должно превышать 5 МБ.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'role.ru' => 'должность (рус.)',
            'name.ru' => 'ФИО (рус.)',
        ];
    }
}
