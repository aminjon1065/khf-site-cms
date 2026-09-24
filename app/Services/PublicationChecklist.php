<?php

namespace App\Services;

use App\Contracts\Workflowable;
use App\Models\Instruction;
use App\Models\News;
use App\Support\ContentLocales;
use App\Support\ContentTitle;
use App\Support\PublicLocale;
use App\Support\RichTextMediaResolver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use Spatie\MediaLibrary\HasMedia;

class PublicationChecklist
{
    /**
     * Checklist label of a language version and the form used in
     * «на … версии сайта».
     *
     * @var array<string, array{0: string, 1: string}>
     */
    private const LANGUAGE_VERSIONS = [
        'tg' => ['Таджикская', 'таджикской'],
        'ru' => ['Русская', 'русской'],
        'en' => ['Английская', 'английской'],
    ];

    public function __construct(private readonly RichTextMediaResolver $richMedia) {}

    /**
     * @return list<array{key: string, label: string, ok: bool, blocking: bool, detail: string|null}>
     */
    public function inspect(Model&Workflowable $subject): array
    {
        $items = [];

        if (method_exists($subject, 'languageCompleteness')) {
            array_push($items, ...$this->languageItems($subject, $subject->languageCompleteness()));
        }

        if ($subject instanceof News && $subject->hasMedia('cover')) {
            $items[] = [
                'key' => 'image_alt',
                'label' => 'Описание обложки',
                'ok' => filled($subject->cover_alt),
                'blocking' => true,
                'detail' => filled($subject->cover_alt) ? null : 'Опишите изображение для людей, которые его не видят.',
            ];
        }

        $pictures = $this->pictureDescriptions($subject);

        if ($pictures['images'] > 0) {
            $items[] = [
                'key' => 'image_descriptions',
                'label' => 'Фото в тексте описаны',
                'ok' => $pictures['undescribed'] === 0,
                // Not blocking: older texts hold photos without descriptions,
                // and publishing a fix must stay possible.
                'blocking' => false,
                'detail' => $pictures['undescribed'] === 0
                    ? null
                    : "Без описания: {$pictures['undescribed']}. Опишите, что на фото, в тексте или в медиатеке — иначе незрячие читатели ничего о нём не узнают.",
            ];
        }

        if ($subject instanceof HasMedia) {
            $mediaItems = $subject->getMedia('*');
            $failed = $mediaItems->filter(
                fn ($media): bool => $media->getCustomProperty('conversion_status') === 'failed',
            );
            $inProgress = $mediaItems->filter(
                fn ($media): bool => in_array(
                    $media->getCustomProperty('conversion_status'),
                    ['pending', 'processing'],
                    true,
                ),
            );

            $items[] = [
                'key' => 'media_ready',
                'label' => 'Обработка медиа завершена',
                'ok' => $failed->isEmpty() && $inProgress->isEmpty(),
                'blocking' => $failed->isNotEmpty(),
                'detail' => match (true) {
                    $failed->isNotEmpty() => "Не удалось обработать файлов: {$failed->count()}",
                    $inProgress->isNotEmpty() => "Файлы ещё обрабатываются: {$inProgress->count()}. Можно опубликовать — на сайте покажется оригинал.",
                    default => null,
                },
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
            'label' => 'Описание для поиска заполнено',
            'ok' => $this->hasSeoDescription($subject),
            'blocking' => false,
            'detail' => $this->hasSeoDescription($subject) ? null : 'Можно опубликовать: поисковики возьмут начало текста.',
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
     * A material may be published in a single language: the public site lists
     * it only on the language versions that have its title and text
     * (PublicLocale), so a missing translation is reported, not enforced.
     * Publication needs at least one version filled completely.
     *
     * @param  array<string, int>  $completeness
     * @return list<array{key: string, label: string, ok: bool, blocking: bool, detail: string|null}>
     */
    private function languageItems(Model $subject, array $completeness): array
    {
        $anyComplete = in_array(100, array_map('intval', $completeness), true);
        $missingTitle = ContentTitle::field($subject) === 'name' ? 'Нет названия' : 'Нет заголовка';
        $missingText = $subject instanceof Instruction ? 'Нет ни шагов, ни текста' : 'Нет текста';

        $items = [[
            'key' => 'translation_any',
            'label' => 'Хотя бы одна языковая версия заполнена',
            'ok' => $anyComplete,
            'blocking' => true,
            'detail' => $anyComplete ? null : 'Заполните все поля материала хотя бы на одном языке.',
        ]];

        foreach (self::LANGUAGE_VERSIONS as $locale => [$version, $siteVersion]) {
            $percent = (int) ($completeness[$locale] ?? 0);
            $required = ContentLocales::isRequired($locale);
            $hasTitle = ContentTitle::in($subject, $locale) !== '';

            // English is optional: an untouched English version is not a
            // checklist item at all, only a started one is reported.
            if (! $required && ! $hasTitle) {
                continue;
            }

            $items[] = [
                'key' => "translation_{$locale}",
                'label' => $required ? "{$version} версия заполнена" : "{$version} версия заполнена (необязательно)",
                'ok' => $percent === 100,
                'blocking' => false,
                'detail' => match (true) {
                    $percent === 100 => null,
                    ! $hasTitle => "{$missingTitle} — на {$siteVersion} версии сайта материал не появится.",
                    ! PublicLocale::hasText($subject, $locale) => "{$missingText} — на {$siteVersion} версии сайта материал не появится.",
                    default => "Заполнена на {$percent}%.",
                },
            ];
        }

        return $items;
    }

    /**
     * Photos in the material's text, in every language, and how many say
     * nothing to readers who can't see them (RichTextMediaResolver).
     *
     * @return array{images: int, undescribed: int}
     */
    private function pictureDescriptions(Model $subject): array
    {
        $total = ['images' => 0, 'undescribed' => 0];

        if (! method_exists($subject, 'getTranslations')
            || ! method_exists($subject, 'isTranslatableAttribute')
            || ! $subject->isTranslatableAttribute('body')) {
            return $total;
        }

        foreach ($subject->getTranslations('body') as $html) {
            if (! is_string($html)) {
                continue;
            }

            $found = $this->richMedia->descriptions($html);
            $total['images'] += $found['images'];
            $total['undescribed'] += $found['undescribed'];
        }

        return $total;
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
