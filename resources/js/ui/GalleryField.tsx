import { Images, Trash2, Upload } from 'lucide-react';
import { useRef } from 'react';
import { MEDIA_LOCKED_NOTE } from '@/lib/domain';
import { Button } from '@/ui/Button';
import { Field } from '@/ui/Field';

/** Уже сохранённый снимок галереи, как его отдаёт форма редактора. */
export interface ExistingGalleryItem {
    id: number;
    title: string;
    preview_url: string;
}

/** Снимок, добавленный из медиатеки, но ещё не отправленный на сервер. */
export interface PendingLibraryItem {
    id: number;
    title: string;
    url: string;
}

/**
 * Фотогалерея материала: несколько снимков каруселью на детальной странице.
 * Порядок — порядок добавления. Удаление помечает существующий файл, а не
 * убирает сразу: пока форма не отправлена, редактор может передумать (тот же
 * контракт, что у вложений). Когда правка уйдёт на согласование (`locked`),
 * снимки только показываются.
 */
export function GalleryField({
    existing,
    addedFiles,
    addedLibrary,
    removed,
    onAddFiles,
    onToggleRemove,
    onOpenPicker,
    locked = false,
    error,
}: {
    existing: ExistingGalleryItem[];
    addedFiles: File[];
    addedLibrary: PendingLibraryItem[];
    removed: number[];
    onAddFiles: (files: File[]) => void;
    onToggleRemove: (id: number) => void;
    onOpenPicker: () => void;
    locked?: boolean;
    error?: string;
}) {
    const fileRef = useRef<HTMLInputElement>(null);

    if (locked) {
        return (
            <Field label="Фотогалерея" error={error}>
                <div className="flex flex-col gap-3">
                    {existing.length > 0 && (
                        <div className="grid grid-cols-[repeat(auto-fill,minmax(96px,1fr))] gap-2">
                            {existing.map((item) => (
                                <img
                                    key={item.id}
                                    src={item.preview_url}
                                    alt={item.title}
                                    loading="lazy"
                                    className="block aspect-[4/3] w-full border border-[var(--color-divider)] object-cover"
                                />
                            ))}
                        </div>
                    )}
                    <p className="wp-locked-note">{MEDIA_LOCKED_NOTE}</p>
                </div>
            </Field>
        );
    }

    return (
        <Field
            label="Фотогалерея"
            hint="JPG, PNG, WebP — до 5 МБ каждый, до 20 снимков. Показываются каруселью на странице материала; порядок — порядок добавления."
            error={error}
        >
            <div className="flex flex-col gap-3">
                {(existing.length > 0 ||
                    addedFiles.length > 0 ||
                    addedLibrary.length > 0) && (
                    <div className="grid grid-cols-[repeat(auto-fill,minmax(96px,1fr))] gap-2">
                        {existing.map((item) => {
                            const isRemoved = removed.includes(item.id);

                            return (
                                <button
                                    type="button"
                                    key={item.id}
                                    className="group relative block overflow-hidden border border-[var(--color-divider)]"
                                    style={{ opacity: isRemoved ? 0.45 : 1 }}
                                    aria-label={
                                        isRemoved
                                            ? `Вернуть снимок «${item.title}»`
                                            : `Удалить снимок «${item.title}»`
                                    }
                                    onClick={() => onToggleRemove(item.id)}
                                >
                                    <img
                                        src={item.preview_url}
                                        alt={item.title}
                                        loading="lazy"
                                        className="block aspect-[4/3] w-full object-cover"
                                        style={{
                                            textDecoration: isRemoved
                                                ? 'line-through'
                                                : undefined,
                                        }}
                                    />
                                    <span
                                        className="absolute inset-0 hidden place-items-center group-hover:grid"
                                        style={{
                                            background:
                                                'color-mix(in srgb, var(--color-bg) 55%, transparent)',
                                        }}
                                    >
                                        <Trash2 size={18} strokeWidth={1.75} />
                                    </span>
                                </button>
                            );
                        })}

                        {addedLibrary.map((item) => (
                            <div
                                key={`lib-${item.id}`}
                                className="relative overflow-hidden border border-dashed border-[var(--color-divider)]"
                                title={item.title}
                            >
                                <img
                                    src={item.url}
                                    alt={item.title}
                                    loading="lazy"
                                    className="block aspect-[4/3] w-full object-cover"
                                />
                                <span className="tag tag-accent absolute bottom-1 left-1">
                                    медиатека
                                </span>
                            </div>
                        ))}

                        {addedFiles.map((file, index) => (
                            <div
                                key={`file-${file.name}-${index}`}
                                className="relative overflow-hidden border border-dashed border-[var(--color-divider)]"
                                title={file.name}
                            >
                                <img
                                    src={URL.createObjectURL(file)}
                                    alt={file.name}
                                    loading="lazy"
                                    className="block aspect-[4/3] w-full object-cover"
                                />
                                <span className="tag tag-outline absolute bottom-1 left-1">
                                    файл
                                </span>
                            </div>
                        ))}
                    </div>
                )}

                <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap' }}>
                    <Button
                        variant="secondary"
                        icon={<Upload size={15} strokeWidth={1.75} />}
                        onClick={() => fileRef.current?.click()}
                    >
                        Загрузить снимки
                    </Button>
                    <Button
                        variant="secondary"
                        icon={<Images size={15} strokeWidth={1.75} />}
                        onClick={onOpenPicker}
                    >
                        Добавить из медиатеки
                    </Button>
                </div>

                <input
                    ref={fileRef}
                    type="file"
                    multiple
                    accept="image/jpeg,image/png,image/webp"
                    hidden
                    onChange={(e) => {
                        onAddFiles(Array.from(e.target.files ?? []));
                        e.target.value = '';
                    }}
                />
            </div>
        </Field>
    );
}
