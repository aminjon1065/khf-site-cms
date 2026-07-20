<?php

namespace App\Http\Resources\Api;

use App\Models\Document;
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
        $files = $this->collectFiles();
        $primary = $this->primaryFile($files, $locale);

        return [
            'id' => $this->id,
            'type' => $this->doc_type->label(),
            'type_value' => $this->doc_type->value,
            'title' => $this->localizedName($locale),
            'number' => $this->number,
            'section' => $this->section,
            'date' => $this->doc_date?->format('d.m.Y'),
            'lang' => implode(' / ', array_map(fn (array $f): string => $f['label'], $files)),
            'size' => $primary['size'] ?? null,
            'href' => $primary['url'] ?? null,
            'files' => $files,
        ];
    }

    /**
     * @return list<array{lang: string, label: string, url: string, ext: string, size: string}>
     */
    private function collectFiles(): array
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
                'size' => $ext.' · '.$this->humanSize((int) $media->size),
            ];
        }

        return $files;
    }

    /**
     * The download shown in the compact "Файл" column: the requested locale's
     * file, else the ru version, else the first available.
     *
     * @param  list<array{lang: string, label: string, url: string, ext: string, size: string}>  $files
     * @return array{lang: string, label: string, url: string, ext: string, size: string}|null
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

        return $byLang[$locale] ?? $byLang['ru'] ?? $files[0];
    }

    private function localizedName(string $locale): string
    {
        $value = $this->getTranslation('name', $locale, false);

        return trim($value) !== '' ? $value : $this->getTranslation('name', 'ru', false);
    }

    private function humanSize(int $bytes): string
    {
        if ($bytes >= 1048576) {
            return str_replace('.', ',', (string) round($bytes / 1048576, 1)).' МБ';
        }

        if ($bytes >= 1024) {
            return str_replace('.', ',', (string) round($bytes / 1024, 1)).' КБ';
        }

        return $bytes.' Б';
    }
}
