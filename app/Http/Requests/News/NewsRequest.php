<?php

namespace App\Http\Requests\News;

use App\Models\News;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class NewsRequest extends FormRequest
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
        /** @var News|null $news */
        $news = $this->route('news');

        return [
            'title' => ['array'],
            'title.ru' => ['required', 'string', 'max:255'],
            'title.tg' => ['nullable', 'string', 'max:255'],
            'title.en' => ['nullable', 'string', 'max:255'],

            'summary' => ['array'],
            'summary.ru' => ['nullable', 'string', 'max:1000'],
            'summary.tg' => ['nullable', 'string', 'max:1000'],
            'summary.en' => ['nullable', 'string', 'max:1000'],

            'body' => ['array'],
            'body.ru' => ['nullable', 'string', 'max:50000'],
            'body.tg' => ['nullable', 'string', 'max:50000'],
            'body.en' => ['nullable', 'string', 'max:50000'],

            'slug' => [
                'nullable', 'string', 'max:255', 'alpha_dash',
                Rule::unique('news', 'slug')->ignore($news?->id),
            ],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'tags' => ['array'],
            'tags.*' => ['integer', 'exists:tags,id'],

            'cover' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'cover_media_id' => ['nullable', 'integer', 'exists:media,id'],
            'cover_remove' => ['boolean'],
            'cover_alt' => ['nullable', 'string', 'max:255'],

            'is_pinned' => ['boolean'],
            'show_on_home' => ['boolean'],

            'seo' => ['array'],
            'seo.title' => ['nullable', 'string', 'max:255'],
            'seo.description' => ['nullable', 'string', 'max:500'],

            'scheduled_at' => ['nullable', 'date'],

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
            'title.ru.required' => 'Укажите заголовок новости на русском языке.',
            'slug.unique' => 'Такой адрес (slug) уже используется другой новостью.',
            'slug.alpha_dash' => 'Адрес может содержать только латинские буквы, цифры и дефисы.',
            'category_id.exists' => 'Выбрана несуществующая категория.',
            'cover.image' => 'Обложка должна быть изображением.',
            'cover.max' => 'Размер обложки не должен превышать 5 МБ.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'title.ru' => 'заголовок (рус.)',
            'slug' => 'адрес',
            'category_id' => 'категория',
            'scheduled_at' => 'дата публикации',
        ];
    }
}
