import { Paperclip, Trash2, Upload } from 'lucide-react';
import { useRef, useState } from 'react';
import { useUploadLimits } from '@/hooks/use-upload-limits';
import { MEDIA_LOCKED_NOTE } from '@/lib/domain';
import { acceptOf, uploadProblem } from '@/lib/uploads';
import { Field } from '@/ui/Field';

/** Уже прикреплённое вложение, как его отдаёт форма редактора. */
export interface ExistingAttachment {
    id: number;
    title: string;
    ext: string;
    size: string;
}

/**
 * Вложения материала: памятки и документы, которые читатель скачивает со
 * страницы. Один компонент на новость и инструкцию — блоки одинаковые, и
 * держать две копии значило бы чинить будущие правки дважды.
 *
 * Удаление помечает существующий файл, а не убирает его сразу: пока форма не
 * отправлена, редактор может передумать, и снятая пометка возвращает файл.
 * Когда правка уйдёт на согласование (`locked`), файлы только показываются.
 */
export function AttachmentsField({
    existing,
    added,
    removed,
    onAdd,
    onToggleRemove,
    locked = false,
    error,
}: {
    existing: ExistingAttachment[];
    added: File[];
    removed: number[];
    onAdd: (files: File[]) => void;
    onToggleRemove: (id: number) => void;
    locked?: boolean;
    error?: string;
}) {
    const fileRef = useRef<HTMLInputElement>(null);
    const limit = useUploadLimits().file;
    // Files turned away before the upload, with the reason.
    const [problems, setProblems] = useState<string[]>([]);

    return (
        <Field
            label="Материалы"
            hint={
                locked
                    ? undefined
                    : `${limit.label} — до ${limit.max_mb} МБ каждый. Показываются на странице списком со ссылкой на скачивание.`
            }
            error={error}
        >
            <div className="flex flex-col gap-2">
                {existing.map((file) => {
                    const isRemoved = removed.includes(file.id);

                    return (
                        <div
                            key={file.id}
                            className="flex items-center gap-2.5 border-b border-[var(--color-divider)] py-2 last:border-b-0"
                            style={{ opacity: isRemoved ? 0.45 : 1 }}
                        >
                            <span className="tag tag-neutral flex-none">
                                {file.ext}
                            </span>
                            <span
                                className="flex-1 text-[13.5px]"
                                style={{
                                    textDecoration: isRemoved
                                        ? 'line-through'
                                        : undefined,
                                }}
                            >
                                {file.title}
                            </span>
                            <span
                                className="flex-none text-xs"
                                style={{ color: 'var(--color-neutral-500)' }}
                            >
                                {file.size}
                            </span>
                            {!locked && (
                                <button
                                    type="button"
                                    className="btn btn-icon"
                                    aria-label={
                                        isRemoved
                                            ? `Вернуть «${file.title}»`
                                            : `Удалить «${file.title}»`
                                    }
                                    aria-pressed={isRemoved}
                                    onClick={() => onToggleRemove(file.id)}
                                >
                                    <Trash2 size={15} strokeWidth={1.5} />
                                </button>
                            )}
                        </div>
                    );
                })}

                {added.map((file, index) => (
                    <div
                        key={`${file.name}-${index}`}
                        className="flex items-center gap-2.5 py-2 text-[13.5px]"
                    >
                        <Paperclip
                            size={15}
                            strokeWidth={1.5}
                            aria-hidden="true"
                        />
                        <span className="flex-1">{file.name}</span>
                        <span
                            className="flex-none text-xs"
                            style={{ color: 'var(--color-neutral-500)' }}
                        >
                            будет загружен
                        </span>
                    </div>
                ))}

                {locked ? (
                    <p className="wp-locked-note">{MEDIA_LOCKED_NOTE}</p>
                ) : (
                    <div>
                        <button
                            type="button"
                            className="btn"
                            onClick={() => fileRef.current?.click()}
                        >
                            <Upload
                                size={15}
                                strokeWidth={1.5}
                                aria-hidden="true"
                            />
                            Добавить файлы
                        </button>
                        <input
                            ref={fileRef}
                            type="file"
                            multiple
                            hidden
                            accept={acceptOf(limit)}
                            onChange={(e) => {
                                const files = [...(e.target.files ?? [])];
                                e.target.value = '';
                                const reasons = files.map((file) =>
                                    uploadProblem(file, limit, 'file'),
                                );
                                setProblems(
                                    reasons.filter(
                                        (reason): reason is string =>
                                            reason !== null,
                                    ),
                                );
                                const accepted = files.filter(
                                    (_, index) => reasons[index] === null,
                                );

                                if (accepted.length > 0) {
                                    onAdd(accepted);
                                }
                            }}
                        />
                        {problems.map((problem) => (
                            <div key={problem} className="wp-field-error">
                                {problem}
                            </div>
                        ))}
                    </div>
                )}
            </div>
        </Field>
    );
}
