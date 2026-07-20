<?php

namespace App\Http\Requests\Announcement;

use App\Enums\AnnouncementKind;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AnnouncementRequest extends FormRequest
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
            'title' => ['array'],
            'title.ru' => ['required', 'string', 'max:255'],
            'title.tg' => ['nullable', 'string', 'max:255'],
            'title.en' => ['nullable', 'string', 'max:255'],

            'body' => ['array'],
            'body.ru' => ['nullable', 'string', 'max:5000'],
            'body.tg' => ['nullable', 'string', 'max:5000'],
            'body.en' => ['nullable', 'string', 'max:5000'],

            'kind' => ['required', Rule::enum(AnnouncementKind::class)],
            'org' => ['nullable', 'string', 'max:255'],
            'deadline' => ['nullable', 'date'],

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
            'title.ru.required' => 'Укажите заголовок объявления на русском языке.',
            'kind.required' => 'Выберите тип объявления.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'title.ru' => 'заголовок (рус.)',
            'kind' => 'тип объявления',
            'deadline' => 'срок подачи',
        ];
    }
}
