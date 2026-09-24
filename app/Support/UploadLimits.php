<?php

namespace App\Support;

/**
 * What the CMS takes as uploads — one set of formats and sizes for every
 * cover, photo, attachment and document — and whether PHP on this server
 * lets them through. PHP's defaults (2 MB per file, 8 MB per request, 20
 * files) are far below what editors upload: a photo from a phone or a
 * scanned order would fail with no fault of theirs. DEPLOYMENT.md §2.10 sets
 * php.ini to these values; the control center warns administrators when it
 * isn't.
 */
final class UploadLimits
{
    /**
     * Photos: covers, illustrations, gallery photos, portraits.
     *
     * @var list<string>
     */
    public const IMAGE_FORMATS = ['jpg', 'jpeg', 'png', 'webp'];

    /**
     * The media library also keeps GIF animations for texts. SVG is left out
     * on purpose: served inline, it can run scripts.
     *
     * @var list<string>
     */
    public const LIBRARY_IMAGE_FORMATS = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

    public const IMAGE_MAX_MB = 10;

    /**
     * Documents and attachments.
     *
     * @var list<string>
     */
    public const FILE_FORMATS = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx'];

    /**
     * The largest file the CMS accepts: a document or an attachment.
     */
    public const FILE_MAX_MB = 20;

    /**
     * One save of a material: a news item may carry a cover, a gallery of up
     * to 20 photos and up to 10 attachments (php.ini `post_max_size`).
     */
    public const REQUEST_MAX_MB = 128;

    /**
     * Files in one save: 1 cover + 20 gallery photos + 10 attachments, with
     * room to spare (php.ini `max_file_uploads`).
     */
    public const FILES_PER_REQUEST = 40;

    private const MB = 1024 * 1024;

    /**
     * @return list<string>
     */
    public static function imageRules(bool $library = false): array
    {
        return [
            'image',
            'mimes:'.implode(',', $library ? self::LIBRARY_IMAGE_FORMATS : self::IMAGE_FORMATS),
            'max:'.self::IMAGE_MAX_MB * 1024,
        ];
    }

    /**
     * @return list<string>
     */
    public static function fileRules(): array
    {
        return [
            'file',
            'mimes:'.implode(',', self::FILE_FORMATS),
            'max:'.self::FILE_MAX_MB * 1024,
        ];
    }

    /**
     * Messages for a photo field: `imageMessages('cover', 'Обложка')`.
     *
     * @return array<string, string>
     */
    public static function imageMessages(string $field, string $label, bool $library = false): array
    {
        $formats = self::describe($library ? self::LIBRARY_IMAGE_FORMATS : self::IMAGE_FORMATS);

        return [
            "{$field}.image" => "{$label}: нужно изображение {$formats}.",
            "{$field}.mimes" => "{$label}: нужно изображение {$formats}.",
            "{$field}.max" => "{$label} больше ".self::IMAGE_MAX_MB.' МБ — уменьшите изображение.',
        ];
    }

    /**
     * Messages for a document or attachment field.
     *
     * @return array<string, string>
     */
    public static function fileMessages(string $field, string $label): array
    {
        return [
            "{$field}.file" => "{$label}: подходят файлы ".self::describe(self::FILE_FORMATS).'.',
            "{$field}.mimes" => "{$label}: подходят файлы ".self::describe(self::FILE_FORMATS).'.',
            "{$field}.max" => "{$label} больше ".self::FILE_MAX_MB.' МБ — сожмите файл или разделите его на части.',
        ];
    }

    /**
     * Formats as people read them: «JPG, PNG или WebP», «PDF, DOC(X) или
     * XLS(X)». A format and its `x` version are one word, JPEG is JPG.
     *
     * @param  list<string>  $formats
     */
    public static function describe(array $formats): string
    {
        $names = [];

        foreach ($formats as $format) {
            if ($format === 'jpeg' && in_array('jpg', $formats, true)) {
                continue;
            }

            if (str_ends_with($format, 'x') && in_array(substr($format, 0, -1), $formats, true)) {
                continue;
            }

            $names[] = match (true) {
                $format === 'webp' => 'WebP',
                in_array($format.'x', $formats, true) => strtoupper($format).'(X)',
                default => strtoupper($format),
            };
        }

        $last = array_pop($names);

        return $names === [] ? (string) $last : implode(', ', $names).' или '.$last;
    }

    /**
     * The formats and sizes for the editors' pickers, hints and checks
     * before an upload starts.
     *
     * @return array{image: array{formats: list<string>, label: string, max_mb: int}, library_image: array{formats: list<string>, label: string, max_mb: int}, file: array{formats: list<string>, label: string, max_mb: int}}
     */
    public static function forClient(): array
    {
        return [
            'image' => [
                'formats' => self::IMAGE_FORMATS,
                'label' => self::describe(self::IMAGE_FORMATS),
                'max_mb' => self::IMAGE_MAX_MB,
            ],
            'library_image' => [
                'formats' => self::LIBRARY_IMAGE_FORMATS,
                'label' => self::describe(self::LIBRARY_IMAGE_FORMATS),
                'max_mb' => self::IMAGE_MAX_MB,
            ],
            'file' => [
                'formats' => self::FILE_FORMATS,
                'label' => self::describe(self::FILE_FORMATS),
                'max_mb' => self::FILE_MAX_MB,
            ],
        ];
    }

    /**
     * What stops uploads the CMS allows, in words for an administrator.
     * Empty when PHP is set up for them.
     *
     * @param  array{upload_max_filesize: string, post_max_size: string, max_file_uploads: string}|null  $ini
     * @return list<string>
     */
    public static function serverProblems(?array $ini = null): array
    {
        $ini ??= [
            'upload_max_filesize' => (string) ini_get('upload_max_filesize'),
            'post_max_size' => (string) ini_get('post_max_size'),
            'max_file_uploads' => (string) ini_get('max_file_uploads'),
        ];
        $problems = [];

        $fileBytes = self::bytes($ini['upload_max_filesize']);

        if ($fileBytes < self::FILE_MAX_MB * self::MB) {
            $problems[] = sprintf(
                'PHP принимает файлы до %1$s (upload_max_filesize = %2$s), а CMS разрешает до %3$d МБ: фото и документы крупнее %1$s не загрузятся.',
                self::megabytes($fileBytes),
                $ini['upload_max_filesize'],
                self::FILE_MAX_MB,
            );
        }

        $requestBytes = self::bytes($ini['post_max_size']);

        // `post_max_size = 0` switches the limit off.
        if ($requestBytes !== 0 && $requestBytes < self::REQUEST_MAX_MB * self::MB) {
            $problems[] = sprintf(
                'За одно сохранение PHP принимает до %s (post_max_size = %s): материал с несколькими фото и файлами не сохранится. Нужно не меньше %d МБ.',
                self::megabytes($requestBytes),
                $ini['post_max_size'],
                self::REQUEST_MAX_MB,
            );
        }

        $files = (int) $ini['max_file_uploads'];

        if ($files < self::FILES_PER_REQUEST) {
            $problems[] = sprintf(
                'PHP принимает не больше %d файлов за одно сохранение (max_file_uploads), а у новости с галереей их бывает больше: лишние пропадут. Нужно не меньше %d.',
                $files,
                self::FILES_PER_REQUEST,
            );
        }

        return $problems;
    }

    /**
     * What one save may weigh on this server, in whole megabytes; null when
     * PHP sets no limit.
     */
    public static function requestLimitMegabytes(): ?int
    {
        $bytes = self::bytes((string) ini_get('post_max_size'));

        return $bytes === 0 ? null : intdiv($bytes, self::MB);
    }

    /**
     * A php.ini size (`2M`, `128M`, `1G`, plain bytes) in bytes.
     */
    private static function bytes(string $value): int
    {
        return trim($value) === '' ? 0 : ini_parse_quantity($value);
    }

    private static function megabytes(int $bytes): string
    {
        return $bytes >= self::MB
            ? intdiv($bytes, self::MB).' МБ'
            : max(1, intdiv($bytes, 1024)).' КБ';
    }
}
