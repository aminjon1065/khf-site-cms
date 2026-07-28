<?php

namespace App\Services;

use App\Contracts\Workflowable;
use App\Models\News;
use App\Models\Setting;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use Spatie\MediaLibrary\HasMedia;

class PublicationChecklist
{
    /**
     * @return list<array{key: string, label: string, ok: bool, blocking: bool, detail: string|null}>
     */
    public function inspect(Model&Workflowable $subject): array
    {
        $items = [];

        if (method_exists($subject, 'languageCompleteness')) {
            $completeness = $subject->languageCompleteness();

            foreach ($this->requiredLocales() as $locale) {
                $percent = (int) ($completeness[$locale] ?? 0);
                $items[] = [
                    'key' => "translation_{$locale}",
                    'label' => "Обязательный перевод {$locale}",
                    'ok' => $percent === 100,
                    'blocking' => true,
                    'detail' => $percent === 100 ? null : "Готовность {$percent}%",
                ];
            }
        }

        if ($subject instanceof News && $subject->hasMedia('cover')) {
            $items[] = [
                'key' => 'image_alt',
                'label' => 'Alt-текст обложки',
                'ok' => filled($subject->cover_alt),
                'blocking' => true,
                'detail' => filled($subject->cover_alt) ? null : 'Опишите изображение для людей, которые его не видят.',
            ];
        }

        if ($subject instanceof HasMedia) {
            $notReady = $subject->getMedia('*')->filter(
                fn ($media): bool => in_array(
                    $media->getCustomProperty('conversion_status'),
                    ['pending', 'processing', 'failed'],
                    true,
                ),
            );
            $items[] = [
                'key' => 'media_ready',
                'label' => 'Обработка медиа завершена',
                'ok' => $notReady->isEmpty(),
                'blocking' => true,
                'detail' => $notReady->isEmpty() ? null : "Не готово файлов: {$notReady->count()}",
            ];
        }

        $invalidLinks = $this->invalidLinks($subject);
        $items[] = [
            'key' => 'links',
            'label' => 'Ссылки имеют безопасный адрес',
            'ok' => $invalidLinks === [],
            'blocking' => true,
            'detail' => $invalidLinks === [] ? null : implode(', ', $invalidLinks),
        ];

        $items[] = [
            'key' => 'seo',
            'label' => 'SEO-описание заполнено',
            'ok' => $this->hasSeoDescription($subject),
            'blocking' => false,
            'detail' => $this->hasSeoDescription($subject) ? null : 'Можно опубликовать, но сниппет будет сформирован автоматически.',
        ];

        return $items;
    }

    /**
     * @throws ValidationException
     */
    public function ensurePublishable(Model&Workflowable $subject): void
    {
        $failed = array_values(array_filter(
            $this->inspect($subject),
            fn (array $item): bool => $item['blocking'] && ! $item['ok'],
        ));

        if ($failed === []) {
            return;
        }

        throw ValidationException::withMessages([
            'publication_checklist' => 'Перед публикацией исправьте: '.implode(
                '; ',
                array_map(fn (array $item): string => $item['label'], $failed),
            ).'.',
        ]);
    }

    /**
     * @return list<string>
     */
    private function requiredLocales(): array
    {
        $configured = Setting::query()
            ->where('group', 'languages')
            ->where('key', 'require_translation')
            ->first()?->value;

        return is_array($configured)
            ? array_values(array_filter($configured, 'is_string'))
            : ['tg', 'ru'];
    }

    /**
     * @return list<string>
     */
    private function invalidLinks(Model $subject): array
    {
        $matches = [];

        foreach ($subject->toArray() as $value) {
            $this->collectInvalidLinks($value, $matches);
        }

        return array_values(array_unique($matches));
    }

    /**
     * @param  list<string>  $invalid
     */
    private function collectInvalidLinks(mixed $value, array &$invalid): void
    {
        if (is_array($value)) {
            foreach ($value as $child) {
                $this->collectInvalidLinks($child, $invalid);
            }

            return;
        }

        if (! is_string($value) || ! str_contains($value, 'href=')) {
            return;
        }

        preg_match_all('/href=["\']([^"\']+)["\']/i', $value, $matches);

        foreach ($matches[1] as $href) {
            if (! preg_match('/^(https?:\/\/|mailto:|tel:|\/|#)/i', $href)) {
                $invalid[] = $href;
            }
        }
    }

    private function hasSeoDescription(Model $subject): bool
    {
        $seo = $subject->getAttribute('seo');

        if (is_array($seo)) {
            return collect($seo)->contains(
                fn (mixed $value): bool => is_array($value) && filled($value['description'] ?? null),
            );
        }

        if (method_exists($subject, 'getTranslations')) {
            $translatable = method_exists($subject, 'getTranslatableAttributes')
                ? $subject->getTranslatableAttributes()
                : [];

            foreach (array_intersect(['seo_description', 'summary', 'body'], $translatable) as $field) {
                if (filled(array_filter($subject->getTranslations($field)))) {
                    return true;
                }
            }
        }

        return false;
    }
}
