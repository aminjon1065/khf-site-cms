<?php

namespace App\Http\Requests\Media;

use App\Support\UploadLimits;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

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
            // SVG is left out on purpose (UploadLimits): served inline, it
            // can run scripts. The rich-text picker takes images only.
            'file' => ['required', ...($this->takesImage()
                ? UploadLimits::imageRules(library: true)
                : UploadLimits::fileRules())],
            'title' => ['nullable', 'string', 'max:255'],
            'alt' => ['nullable', 'string', 'max:255'],
            'is_decorative' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $images = UploadLimits::describe(UploadLimits::LIBRARY_IMAGE_FORMATS);
        $files = UploadLimits::describe(UploadLimits::FILE_FORMATS);
        $formats = $this->routeIs('media.upload')
            ? "В текст можно вставить изображение {$images}."
            : "Подходят изображения {$images} и документы {$files}.";

        return [
            'file.required' => 'Выберите файл для загрузки.',
            'file.image' => $formats,
            'file.file' => $formats,
            'file.mimes' => $formats,
            'file.max' => $this->takesImage()
                ? 'Изображение больше '.UploadLimits::IMAGE_MAX_MB.' МБ — уменьшите его.'
                : 'Файл больше '.UploadLimits::FILE_MAX_MB.' МБ — сожмите его или разделите на части.',
        ];
    }

    /**
     * The picker in the text editor takes only images; the media library
     * page takes an image by the image rules and anything else as a document.
     */
    private function takesImage(): bool
    {
        if ($this->routeIs('media.upload')) {
            return true;
        }

        $file = $this->file('file');

        return $file instanceof UploadedFile
            && str_starts_with((string) $file->getMimeType(), 'image/');
    }
}
