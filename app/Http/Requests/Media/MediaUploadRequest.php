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
        $fileRules = $this->routeIs('media.upload')
            ? ['required', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:10240']
            : [
                'required', 'file',
                'mimes:jpg,jpeg,png,webp,gif,pdf,doc,docx,xls,xlsx',
                'max:15360',
            ];

        return [
            // SVG is intentionally excluded because it can execute scripts when
            // served inline. The rich-text picker additionally accepts images only.
            'file' => $fileRules,
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
            'file.image' => 'В редактор можно загружать только изображения.',
            'file.max' => 'Файл слишком большой (максимум 15 МБ).',
        ];
    }
}
