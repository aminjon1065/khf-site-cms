import { Image as ImageIcon, Images, Upload, Wand2, X } from 'lucide-react';
import { useRef } from 'react';
import type { ReactNode } from 'react';
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

/**
 * A material's cover: a preview, «Загрузить» from the computer and
 * «Медиатека». The form keeps the picked file and shows what `src` gives.
 */
export function CoverField({
    src,
    alt,
    removable,
    removed,
    onRemovedChange,
    onFile,
    onOpenLibrary,
    error,
    children,
}: {
    /** What to show: the fresh pick, else the saved cover; null for none. */
    src: string | null;
    alt?: string;
    /** The material has a saved cover that can be removed. */
    removable: boolean;
    removed: boolean;
    onRemovedChange: (removed: boolean) => void;
    onFile: (file: File) => void;
    onOpenLibrary: () => void;
    error?: string;
    /** Fields about the picture (its description, a caption). */
    children?: ReactNode;
}) {
    const fileRef = useRef<HTMLInputElement>(null);

    return (
        <>
            {src ? (
                <div className="wp-cover-preview-card">
                    <img
                        src={src}
                        alt={alt || 'Обложка'}
                        className="wp-cover-img"
                    />
                </div>
            ) : (
                <button
                    type="button"
                    className="wp-cover-placeholder"
                    onClick={onOpenLibrary}
                >
                    <ImageIcon size={28} strokeWidth={1.5} />
                    <span>Выбрать обложку</span>
                </button>
            )}

            <input
                ref={fileRef}
                type="file"
                accept="image/png,image/jpeg,image/webp"
                hidden
                onChange={(e) => {
                    const file = e.target.files?.[0];

                    if (file) {
                        onFile(file);
                    }

                    e.target.value = '';
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
                    onClick={onOpenLibrary}
                >
                    Медиатека
                </Button>
            </div>

            {/* Outside the preview: once ticked, the preview goes away and the
                choice must stay visible to be undone. */}
            {removable && (
                <Checkbox
                    label="Удалить сохранённую обложку"
                    checked={removed}
                    onChange={(e) => onRemovedChange(e.target.checked)}
                />
            )}

            {error && <div className="wp-field-error">{error}</div>}

            {children}
        </>
    );
}
