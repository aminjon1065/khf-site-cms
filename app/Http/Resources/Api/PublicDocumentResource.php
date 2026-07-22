<?php

namespace App\Http\Resources\Api;

use App\Models\Document;
use App\Support\PublicApiLabels;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Public DTO for an official document (the /documents table on the Next.js
 * site). Exposes per-language downloadable files with human-readable sizes and
 * absolute URLs. Internal editorial fields are never exposed.
 *
 * @mixin Document
 */
class PublicDocumentResource extends JsonResource
{
    /**
     * @var list<string>
     */
    private const LOCALES = ['tg', 'ru', 'en'];

    /**
     * @var array<string, string>
     */
    private const LABELS = ['tg' => 'ТҶ', 'ru' => 'РУ', 'en' => 'EN'];

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $locale = app()->getLocale();
        $files = $this->collectFiles($locale);
        $primary = $this->primaryFile($files, $locale);

        return [
            'id' => $this->id,
            'type' => PublicApiLabels::get('document_type', $this->doc_type->value, $locale),
            'type_value' => $this->doc_type->value,
            'title' => $this->localizedName($locale),
            'number' => $this->number,
            'section' => $this->section,
            'date' => $this->doc_date?->format('d.m.Y'),
            'date_iso' => $this->doc_date?->toDateString(),
            'lang' => implode(' / ', array_map(fn (array $f): string => $f['label'], $files)),
            'size' => $primary['size'] ?? null,
            'href' => $primary['url'] ?? null,
            'files' => $files,
        ];
    }

    /**
     * @return list<array{lang: string, label: string, url: string, ext: string, size: string, size_bytes: int}>
     */
    private function collectFiles(string $requestedLocale): array
    {
        $files = [];

        foreach (self::LOCALES as $locale) {
            $media = $this->getFirstMedia("file_{$locale}");

            if ($media === null) {
                continue;
            }

            $ext = strtoupper(pathinfo($media->file_name, PATHINFO_EXTENSION) ?: 'FILE');

            $files[] = [
                'lang' => $locale,
                'label' => self::LABELS[$locale],
                'url' => $media->getFullUrl(),
                'ext' => $ext,
                'size' => $ext.' · '.$this->humanSize((int) $media->size, $requestedLocale),
                'size_bytes' => (int) $media->size,
            ];
        }

        return $files;
    }

    /**
     * The primary download is strictly the requested locale's file. Other
     * available translations remain explicitly listed in `files`.
     *
     * @param  list<array{lang: string, label: string, url: string, ext: string, size: string, size_bytes: int}>  $files
     * @return array{lang: string, label: string, url: string, ext: string, size: string, size_bytes: int}|null
     */
    private function primaryFile(array $files, string $locale): ?array
    {
        if ($files === []) {
            return null;
        }

        $byLang = [];

        foreach ($files as $file) {
            $byLang[$file['lang']] = $file;
        }

        return $byLang[$locale] ?? null;
    }

    private function localizedName(string $locale): string
    {
        return (string) $this->getTranslation('name', $locale, false);
    }

    private function humanSize(int $bytes, string $locale): string
    {
        $units = $locale === 'en'
            ? ['mb' => 'MB', 'kb' => 'KB', 'b' => 'B']
            : ['mb' => 'МБ', 'kb' => 'КБ', 'b' => 'Б'];

        if ($bytes >= 1048576) {
            return str_replace('.', $locale === 'en' ? '.' : ',', (string) round($bytes / 1048576, 1)).' '.$units['mb'];
        }

        if ($bytes >= 1024) {
            return str_replace('.', $locale === 'en' ? '.' : ',', (string) round($bytes / 1024, 1)).' '.$units['kb'];
        }

        return $bytes.' '.$units['b'];
    }
}
