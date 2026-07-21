<?php

namespace App\Http\Requests\Project;

use App\Enums\ProjectStatus;
use App\Models\Project;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProjectRequest extends FormRequest
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
        /** @var Project|null $project */
        $project = $this->route('project');

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
            'body.ru' => ['nullable', 'string', 'max:20000'],
            'body.tg' => ['nullable', 'string', 'max:20000'],
            'body.en' => ['nullable', 'string', 'max:20000'],

            'slug' => [
                'nullable', 'string', 'max:255', 'alpha_dash',
                Rule::unique('projects', 'slug')->ignore($project?->id),
            ],
            'lifecycle_status' => ['required', Rule::enum(ProjectStatus::class)],
            'code' => ['nullable', 'string', 'max:100'],
            'years' => ['nullable', 'string', 'max:100'],
            'customer' => ['nullable', 'string', 'max:255'],
            'partner' => ['nullable', 'string', 'max:255'],
            'budget' => ['nullable', 'string', 'max:255'],
            'sort' => ['nullable', 'integer', 'min:0', 'max:9999'],

            // goals: { ru|tg|en: string[] }
            'goals' => ['nullable', 'array'],
            'goals.*' => ['array'],
            'goals.*.*' => ['nullable', 'string', 'max:1000'],

            // timeline: [{ date, text, tone }]
            'timeline' => ['nullable', 'array'],
            'timeline.*.date' => ['nullable', 'string', 'max:100'],
            'timeline.*.text' => ['nullable', 'string', 'max:1000'],
            'timeline.*.tone' => ['nullable', 'in:success,info,warning,danger,neutral'],

            'direction' => ['nullable', 'array'],
            'direction.address' => ['nullable', 'string', 'max:255'],
            'direction.phone' => ['nullable', 'string', 'max:100'],
            'direction.email' => ['nullable', 'string', 'max:255'],

            'cover' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'cover_media_id' => ['nullable', 'integer', 'exists:media,id'],
            'cover_remove' => ['boolean'],

            'publish_mode' => ['nullable', 'in:now,review'],
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
            'title.ru.required' => 'Укажите название проекта на русском языке.',
            'lifecycle_status.required' => 'Выберите статус проекта.',
            'slug.unique' => 'Такой адрес (slug) уже используется другим проектом.',
            'slug.alpha_dash' => 'Адрес может содержать только латинские буквы, цифры и дефисы.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'title.ru' => 'название (рус.)',
            'lifecycle_status' => 'статус проекта',
            'slug' => 'адрес',
        ];
    }
}
