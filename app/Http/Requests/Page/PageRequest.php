<?php

namespace App\Http\Requests\Page;

use App\Models\Page;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PageRequest extends FormRequest
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
        /** @var Page|null $page */
        $page = $this->route('page');

        return [
            'title' => ['array'],
            'title.ru' => ['required', 'string', 'max:255'],
            'title.tg' => ['nullable', 'string', 'max:255'],
            'title.en' => ['nullable', 'string', 'max:255'],

            'body' => ['array'],
            'body.ru' => ['nullable', 'string', 'max:50000'],
            'body.tg' => ['nullable', 'string', 'max:50000'],
            'body.en' => ['nullable', 'string', 'max:50000'],

            'slug' => [
                'nullable', 'string', 'max:255', 'alpha_dash',
                Rule::unique('pages', 'slug')->ignore($page?->id),
            ],
            'parent_id' => [
                'nullable', 'integer',
                Rule::exists('pages', 'id')->whereNot('id', $page?->id),
            ],
            'sort' => ['nullable', 'integer', 'min:0', 'max:9999'],

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
            'title.ru.required' => 'Укажите заголовок страницы на русском языке.',
            'slug.unique' => 'Такой адрес (slug) уже используется другой страницей.',
            'slug.alpha_dash' => 'Адрес может содержать только латинские буквы, цифры и дефисы.',
            'parent_id.exists' => 'Выбранная родительская страница не найдена.',
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
        ];
    }
}
