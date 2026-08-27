<?php

namespace App\Support;

use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Вложения материала для публичного API: памятки и документы в PDF.
 *
 * Форма повторяет `files` у документа (`ext` + человекочитаемый размер), чтобы
 * публичная часть не учила второй формат под то же самое — ссылку на файл с
 * типом и весом.
 *
 * Имя берётся из `name` медиа: редактор задаёт его в медиатеке, и это то, что
 * человек увидит в списке. Имя файла на диске для читателя бессмысленно.
 */
final class PublicAttachments
{
    /**
     * @return list<array{title: string, url: string, ext: string, size: string, size_bytes: int}>
     */
    public static function fromModel(HasMedia $model, string $locale, string $collection = 'attachments'): array
    {
        // array_values над обычным массивом, а не Collection::values(): так
        // список гарантирован типом, а не предположением.
        return array_values(array_map(
            static function (Media $media) use ($locale): array {
                $ext = strtoupper(pathinfo($media->file_name, PATHINFO_EXTENSION) ?: 'FILE');
                $title = trim((string) $media->name);

                return [
                    'title' => $title !== '' ? $title : $media->file_name,
                    'url' => $media->getFullUrl(),
                    'ext' => $ext,
                    'size' => FileSize::human((int) $media->size, $locale),
                    'size_bytes' => (int) $media->size,
                ];
            },
            $model->getMedia($collection)->all(),
        ));
    }
}
