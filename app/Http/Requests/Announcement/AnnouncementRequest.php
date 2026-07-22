<?php

namespace App\Http\Requests\Announcement;

use App\Enums\AnnouncementKind;
use App\Models\Announcement;
use App\Rules\SafePublicUrl;
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
        /** @var Announcement|null $announcement */
        $announcement = $this->route('announcement');

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
            'slug' => [
                'nullable', 'string', 'max:255', 'alpha_dash',
                Rule::unique('announcements', 'slug')->ignore($announcement?->id),
            ],
            'application_url' => ['nullable', 'string', 'max:2048', new SafePublicUrl],

            'publish_mode' => ['nullable', 'in:now,review'],
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
            'slug.unique' => 'Такой адрес объявления уже используется.',
            'slug.alpha_dash' => 'Адрес может содержать только латинские буквы, цифры и дефисы.',
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
            'slug' => 'адрес объявления',
            'application_url' => 'ссылка для подачи заявки',
        ];
    }
}
