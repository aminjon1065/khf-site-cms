import { Image as ImageIcon, Images, Upload, Wand2, X } from 'lucide-react';
import { useRef, useState } from 'react';
import type { ReactNode } from 'react';
import { useUploadLimits } from '@/hooks/use-upload-limits';
import { MEDIA_LOCKED_NOTE } from '@/lib/domain';
import { acceptOf, limitHint, uploadProblem } from '@/lib/uploads';
import { Button } from '@/ui/Button';
import { Checkbox, Field, Input } from '@/ui/Field';

/**
 * The editor's settings panel, as in the WordPress block editor: the
 * material's name with a close button, then sections. Sits next to the
 * canvas of EditorialFormShell's `gutenberg` variant.
 */
export function EditorInspector({
    label,
    title,
    onClose,
    children,
}: {
    /** Accessible name of the panel. */
    label: string;
    /** What the panel is about, shown in its header: an icon and a word. */
    title: ReactNode;
    onClose: () => void;
    children: ReactNode;
}) {
    return (
        <aside className="wp-inspector" aria-label={label}>
            <div className="wp-inspector-header">
                <div className="wp-inspector-tabs">
                    <span className="wp-inspector-tab is-active">{title}</span>
                </div>
                <button
                    type="button"
                    className="wp-inspector-close"
                    title="Скрыть панель"
                    aria-label="Скрыть панель"
                    onClick={onClose}
                >
                    <X size={16} strokeWidth={1.75} />
                </button>
            </div>
            <div className="wp-inspector-body">{children}</div>
        </aside>
    );
}

/** One group of settings in the editor's panel. */
export function InspectorSection({
    title,
    children,
}: {
    title: string;
    children: ReactNode;
}) {
    return (
        <section className="wp-inspector-section">
            <h2 className="wp-inspector-section-title">{title}</h2>
            <div className="wp-inspector-section-content">{children}</div>
        </section>
    );
}

/**
 * The address of a material's page on the site: made from the title, and
 * editable. Below it, the link as readers will see it.
 */
export function SlugField({
    id,
    value,
    onChange,
    onGenerate,
    prefix,
    placeholder,
    error,
}: {
    id: string;
    value: string;
    onChange: (value: string) => void;
    /** Makes the address from the title. */
    onGenerate: () => void;
    /** The site address before the slug, e.g. `khf.tj/ru/projects/`. */
    prefix: string;
    placeholder: string;
    error?: string;
}) {
    return (
        <>
            <Field
                label="Адрес ссылки"
                htmlFor={id}
                hint="Составляется из заголовка; можно изменить."
                error={error}
            >
                <div style={{ display: 'flex', gap: 6 }}>
                    <Input
                        id={id}
                        value={value}
                        onChange={(e) => onChange(e.target.value)}
                        hasError={!!error}
                        placeholder={placeholder}
                        style={{ flex: 1 }}
                    />
                    <Button
                        type="button"
                        variant="secondary"
                        size="sm"
                        onClick={onGenerate}
                        title="Составить адрес из заголовка"
                        icon={<Wand2 size={13} />}
                    >
                        Из заголовка
                    </Button>
                </div>
            </Field>
            <div className="wp-permalink-preview">
                <span className="wp-permalink-url">
                    {prefix}
                    {value || placeholder}
                </span>
            </div>
        </>
    );
}

const PICTURE_WORDS = {
    cover: {
        name: 'Обложка',
        pick: 'Выбрать обложку',
        none: 'Обложки нет.',
        remove: 'Удалить сохранённую обложку',
    },
    illustration: {
        name: 'Иллюстрация',
        pick: 'Выбрать иллюстрацию',
        none: 'Иллюстрации нет.',
        remove: 'Удалить сохранённую иллюстрацию',
    },
} as const;

/**
 * A material's cover (or an instruction's illustration): a preview,
 * «Загрузить» from the computer and «Медиатека». The form keeps the picked
 * file and shows what `src` gives. Locked, it only shows the picture.
 */
export function CoverField({
    kind = 'cover',
    src,
    alt,
    removable,
    removed,
    onRemovedChange,
    onFile,
    onOpenLibrary,
    locked = false,
    error,
    children,
}: {
    kind?: keyof typeof PICTURE_WORDS;
    /** What to show: the fresh pick, else the saved picture; null for none. */
    src: string | null;
    alt?: string;
    /** The material has a saved picture that can be removed. */
    removable: boolean;
    removed: boolean;
    onRemovedChange: (removed: boolean) => void;
    onFile: (file: File) => void;
    onOpenLibrary: () => void;
    /** The edit goes to approval: the picture can't be changed here. */
    locked?: boolean;
    error?: string;
    /** Fields about the picture (its description, a caption). */
    children?: ReactNode;
}) {
    const fileRef = useRef<HTMLInputElement>(null);
    const words = PICTURE_WORDS[kind];
    const limit = useUploadLimits().image;
    // A file turned away before the upload: the server's error comes later.
    const [problem, setProblem] = useState<string | null>(null);

    if (locked) {
        return (
            <>
                {src ? (
                    <div className="wp-cover-preview-card">
                        <img
                            src={src}
                            alt={alt || words.name}
                            className="wp-cover-img"
                        />
                    </div>
                ) : (
                    <p className="wp-locked-note">{words.none}</p>
                )}
                <p className="wp-locked-note">{MEDIA_LOCKED_NOTE}</p>
                {src && children}
            </>
        );
    }

    return (
        <>
            {src ? (
                <div className="wp-cover-preview-card">
                    <img
                        src={src}
                        alt={alt || words.name}
                        className="wp-cover-img"
                    />
                </div>
            ) : (
                <button
                    type="button"
                    className="wp-cover-placeholder"
                    onClick={() => {
                        setProblem(null);
                        onOpenLibrary();
                    }}
                >
                    <ImageIcon size={28} strokeWidth={1.5} />
                    <span>{words.pick}</span>
                </button>
            )}

            <input
                ref={fileRef}
                type="file"
                accept={acceptOf(limit)}
                hidden
                onChange={(e) => {
                    const file = e.target.files?.[0];
                    e.target.value = '';

                    if (!file) {
                        return;
                    }

                    const reason = uploadProblem(file, limit, 'image');
                    setProblem(reason);

                    if (reason === null) {
                        onFile(file);
                    }
                }}
            />

            <div className="wp-btn-row">
                <Button
                    variant="secondary"
                    size="sm"
                    icon={<Upload size={14} />}
                    onClick={() => fileRef.current?.click()}
                >
                    Загрузить
                </Button>
                <Button
                    variant="secondary"
                    size="sm"
                    icon={<Images size={14} />}
                    onClick={() => {
                        setProblem(null);
                        onOpenLibrary();
                    }}
                >
                    Медиатека
                </Button>
            </div>
            <span className="ui-hint">{limitHint(limit)}</span>

            {/* Outside the preview: once ticked, the preview goes away and the
                choice must stay visible to be undone. */}
            {removable && (
                <Checkbox
                    label={words.remove}
                    checked={removed}
                    onChange={(e) => onRemovedChange(e.target.checked)}
                />
            )}

            {(problem ?? error) && (
                <div className="wp-field-error">{problem ?? error}</div>
            )}

            {children}
        </>
    );
}
