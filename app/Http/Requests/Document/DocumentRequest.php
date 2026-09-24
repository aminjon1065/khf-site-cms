<?php

namespace App\Http\Requests\Document;

use App\Enums\DocType;
use App\Rules\FilledInAnyLocale;
use App\Support\UploadLimits;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DocumentRequest extends FormRequest
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
        $file = ['nullable', ...UploadLimits::fileRules()];

        return [
            'name' => ['array', new FilledInAnyLocale('Укажите название документа хотя бы на одном языке.')],
            'name.ru' => ['nullable', 'string', 'max:255'],
            'name.tg' => ['nullable', 'string', 'max:255'],
            'name.en' => ['nullable', 'string', 'max:255'],

            'doc_type' => ['required', Rule::enum(DocType::class)],
            'number' => ['nullable', 'string', 'max:100'],
            'doc_date' => ['nullable', 'date'],
            'section' => ['nullable', 'string', 'max:255'],

            'file_tg' => $file,
            'file_ru' => $file,
            'file_en' => $file,
            'file_tg_remove' => ['boolean'],
            'file_ru_remove' => ['boolean'],
            'file_en_remove' => ['boolean'],

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
            'doc_type.required' => 'Выберите тип документа.',
            ...UploadLimits::fileMessages('file_tg', 'Файл (ТҶ)'),
            ...UploadLimits::fileMessages('file_ru', 'Файл (РУ)'),
            ...UploadLimits::fileMessages('file_en', 'Файл (EN)'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name.ru' => 'название (рус.)',
            'doc_type' => 'тип документа',
            'doc_date' => 'дата документа',
        ];
    }
}
