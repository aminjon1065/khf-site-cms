<?php

namespace App\Http\Requests\News;

use App\Models\News;
use App\Rules\FilledInAnyLocale;
use App\Support\Slug;
use App\Support\UploadLimits;
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
            'title' => ['array', new FilledInAnyLocale('Укажите заголовок новости хотя бы на одном языке.')],
            'title.ru' => ['nullable', 'string', 'max:255'],
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
                'nullable', 'string', 'max:'.Slug::MAX_LENGTH, 'alpha_dash',
                Rule::unique('news', 'slug')->ignore($news?->id),
            ],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'tags' => ['array'],
            'tags.*' => ['integer', 'exists:tags,id'],

            'cover' => ['nullable', ...UploadLimits::imageRules()],
            'cover_media_id' => ['nullable', 'integer', 'exists:media,id'],
            'cover_remove' => ['boolean'],

            // Фотогалерея материала: те же форматы, что у обложки. Добавление —
            // файлами или копией из медиатеки, удаление — по идентификаторам,
            // чтобы правка одного снимка не трогала остальные.
            'gallery' => ['nullable', 'array', 'max:20'],
            'gallery.*' => UploadLimits::imageRules(),
            'gallery_media_ids' => ['nullable', 'array', 'max:20'],
            'gallery_media_ids.*' => ['integer', 'exists:media,id'],
            'gallery_remove' => ['nullable', 'array'],
            'gallery_remove.*' => ['integer'],

            // Вложения: памятки и материалы. Только документы — картинки для
            // этого есть обложка и медиатека, а исполняемые файлы на портале
            // ведомства недопустимы.
            'attachments' => ['nullable', 'array', 'max:10'],
            'attachments.*' => UploadLimits::fileRules(),
            'attachments_remove' => ['nullable', 'array'],
            'attachments_remove.*' => ['integer'],
            'cover_alt' => ['nullable', 'string', 'max:255'],
            'cover_caption' => ['nullable', 'string', 'max:500'],

            'is_pinned' => ['boolean'],
            'show_on_home' => ['boolean'],

            'seo' => ['array'],
            'seo.ru' => ['array'],
            'seo.tg' => ['array'],
            'seo.en' => ['array'],
            'seo.ru.title' => ['nullable', 'string', 'max:255'],
            'seo.tg.title' => ['nullable', 'string', 'max:255'],
            'seo.en.title' => ['nullable', 'string', 'max:255'],
            'seo.ru.description' => ['nullable', 'string', 'max:500'],
            'seo.tg.description' => ['nullable', 'string', 'max:500'],
            'seo.en.description' => ['nullable', 'string', 'max:500'],

            'scheduled_at' => [
                Rule::requiredIf(fn (): bool => $this->input('action') === 'submit' && $this->input('publish_mode') === 'schedule'),
                'nullable',
                'date',
                'after:now',
            ],

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
            'slug.unique' => 'Такой адрес (slug) уже используется другой новостью.',
            'slug.alpha_dash' => 'Адрес может содержать только латинские буквы, цифры и дефисы.',
            'category_id.exists' => 'Выбрана несуществующая категория.',
            ...UploadLimits::imageMessages('cover', 'Обложка'),
            'gallery.max' => 'Не больше 20 снимков в галерее.',
            ...UploadLimits::imageMessages('gallery.*', 'Снимок галереи'),
            'attachments.max' => 'Не больше 10 вложений.',
            ...UploadLimits::fileMessages('attachments.*', 'Вложение'),
            'scheduled_at.required' => 'Укажите дату плановой публикации.',
            'scheduled_at.after' => 'Дата плановой публикации должна быть в будущем.',
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
