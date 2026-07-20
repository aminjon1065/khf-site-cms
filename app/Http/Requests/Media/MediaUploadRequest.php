<?php

namespace App\Http\Requests\Media;

use Illuminate\Foundation\Http\FormRequest;

class MediaUploadRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Authorization is enforced in the controller.
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'file' => [
                'required', 'file',
                // SVG is intentionally excluded — it can embed scripts (stored
                // XSS) when served inline from the public disk.
                'mimes:jpg,jpeg,png,webp,gif,pdf,doc,docx,xls,xlsx',
                'max:15360', // 15 MB
            ],
            'title' => ['nullable', 'string', 'max:255'],
            'alt' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.required' => 'Выберите файл для загрузки.',
            'file.mimes' => 'Недопустимый тип файла. Разрешены изображения и документы.',
            'file.max' => 'Файл слишком большой (максимум 15 МБ).',
        ];
    }
}
