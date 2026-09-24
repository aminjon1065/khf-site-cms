/**
 * The formats and sizes the server takes (App\Support\UploadLimits), and the
 * check an upload goes through before it starts: nobody should wait for a
 * large file only to hear it can't go through. No imports: the module is
 * unit-tested under plain Node.
 */

export interface UploadLimit {
    formats: string[];
    /** The formats as people read them: «JPG, PNG или WebP». */
    label: string;
    max_mb: number;
}

export interface UploadLimits {
    /** Covers, illustrations, gallery photos, portraits. */
    image: UploadLimit;
    /** The media library also keeps GIF animations for texts. */
    library_image: UploadLimit;
    /** Documents and attachments. */
    file: UploadLimit;
}

/** The `accept` attribute of a file input: `.jpg,.jpeg,.png,.webp`. */
export function acceptOf(...limits: UploadLimit[]): string {
    return limits
        .flatMap((limit) => limit.formats)
        .map((format) => `.${format}`)
        .join(',');
}

/** «JPG, PNG или WebP до 10 МБ» — for a hint under an upload button. */
export function limitHint(limit: UploadLimit): string {
    return `${limit.label} до ${limit.max_mb} МБ`;
}

/**
 * Why the server would turn the file away, or null. By the file's extension
 * and size — the server then checks the content too.
 */
export function uploadProblem(
    file: File,
    limit: UploadLimit,
    kind: 'image' | 'file',
): string | null {
    const extension = file.name.includes('.')
        ? (file.name.split('.').pop() ?? '').toLowerCase()
        : '';

    if (!limit.formats.includes(extension)) {
        return kind === 'image'
            ? `«${file.name}»: нужно изображение ${limit.label}.`
            : `«${file.name}»: подходят файлы ${limit.label}.`;
    }

    if (file.size > limit.max_mb * 1024 * 1024) {
        return kind === 'image'
            ? `«${file.name}» больше ${limit.max_mb} МБ — уменьшите изображение.`
            : `«${file.name}» больше ${limit.max_mb} МБ — сожмите файл или разделите его на части.`;
    }

    return null;
}
