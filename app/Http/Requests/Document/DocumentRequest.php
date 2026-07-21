<?php

namespace App\Http\Requests\Document;

use App\Enums\DocType;
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
        $file = ['nullable', 'file', 'mimes:pdf,doc,docx,xls,xlsx,ppt,pptx', 'max:20480'];

        return [
            'name' => ['array'],
            'name.ru' => ['required', 'string', 'max:255'],
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
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.ru.required' => 'Укажите название документа на русском языке.',
            'doc_type.required' => 'Выберите тип документа.',
            'file_tg.mimes' => 'Недопустимый формат файла (тадж.). Разрешены PDF, DOC(X), XLS(X), PPT(X).',
            'file_ru.mimes' => 'Недопустимый формат файла (рус.). Разрешены PDF, DOC(X), XLS(X), PPT(X).',
            'file_en.mimes' => 'Недопустимый формат файла (англ.). Разрешены PDF, DOC(X), XLS(X), PPT(X).',
            'file_tg.max' => 'Файл (тадж.) не должен превышать 20 МБ.',
            'file_ru.max' => 'Файл (рус.) не должен превышать 20 МБ.',
            'file_en.max' => 'Файл (англ.) не должен превышать 20 МБ.',
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
