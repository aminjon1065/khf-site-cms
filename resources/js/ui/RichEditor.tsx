import { Placeholder } from '@tiptap/extension-placeholder';
import { TableKit } from '@tiptap/extension-table';
import { TextAlign } from '@tiptap/extension-text-align';
import { Color, TextStyle } from '@tiptap/extension-text-style';
import { Youtube } from '@tiptap/extension-youtube';
import type { EditorView } from '@tiptap/pm/view';
import { EditorContent, useEditor } from '@tiptap/react';
import type { Editor } from '@tiptap/react';
import { StarterKit } from '@tiptap/starter-kit';
import {
    AlignCenter,
    AlignJustify,
    AlignLeft,
    AlignRight,
    Baseline,
    Bold,
    Columns3,
    Heading2,
    Heading3,
    Heading4,
    Image as ImageIcon,
    Italic,
    Link2,
    Link2Off,
    List,
    ListOrdered,
    Minus,
    Quote,
    Redo2,
    RemoveFormatting,
    Rows3,
    Strikethrough,
    Table as TableIcon,
    Trash2,
    Underline,
    Undo2,
    Video,
} from 'lucide-react';
import { useEffect, useReducer, useState } from 'react';
import type { ReactNode } from 'react';
import { postForm } from '@/lib/http';
import { MediaPicker } from './MediaPicker';
import type { MediaItem } from './MediaPicker';
import { RichImage } from './rich-image';
import type { ImageAlign, ImageSize } from './rich-image';

interface Props {
    value: string;
    onChange: (html: string) => void;
    placeholder?: string;
}

/** Кнопка тулбара. */
function Btn({
    icon,
    label,
    active,
    disabled,
    onClick,
}: {
    icon: ReactNode;
    label: string;
    active?: boolean;
    disabled?: boolean;
    onClick: () => void;
}) {
    return (
        <button
            type="button"
            className={`re-btn${active ? 'is-active' : ''}`}
            title={label}
            aria-label={label}
            aria-pressed={active}
            disabled={disabled}
            onMouseDown={(e) => e.preventDefault()} // не терять выделение
            onClick={onClick}
        >
            {icon}
        </button>
    );
}

function Sep() {
    return <span className="re-sep" aria-hidden />;
}

/** Палитра цветов текста (значения работают и в редакторе, и на сайте). */
const TEXT_COLORS: { label: string; value: string }[] = [
    { label: 'Красный', value: '#b3362a' },
    { label: 'Оранжевый', value: '#b5651d' },
    { label: 'Зелёный', value: '#2e7d46' },
    { label: 'Синий', value: '#416180' },
    { label: 'Серый', value: '#5d5d60' },
];

/** Параметры вставки изображения (как в WordPress). */
const IMG_ALIGN: { value: ImageAlign; label: string }[] = [
    { value: null, label: 'Без обтекания' },
    { value: 'left', label: 'Слева' },
    { value: 'center', label: 'По центру' },
    { value: 'right', label: 'Справа' },
];
const IMG_SIZE: { value: ImageSize; label: string }[] = [
    { value: 'small', label: 'Маленький' },
    { value: 'medium', label: 'Средний' },
    { value: 'large', label: 'Большой' },
    { value: 'full', label: 'Полный' },
];

/**
 * Загружает картинки в медиатеку и вставляет их с позиции `pos`. Работает
 * напрямую через ProseMirror-view (drag-and-drop / вставка из буфера).
 */
async function uploadImagesAt(
    view: EditorView,
    files: File[],
    pos: number,
): Promise<void> {
    const imageType = view.state.schema.nodes.image;

    if (!imageType) {
        return;
    }

    let at = pos;

    for (const file of files) {
        try {
            const form = new FormData();
            form.append('file', file);
            form.append('title', file.name);
            const res = await postForm<{ data: MediaItem }>(
                '/media/library',
                form,
            );
            view.dispatch(
                view.state.tr.insert(
                    at,
                    imageType.create({
                        src: res.data.url,
                        alt: res.data.name ?? '',
                        srcset: res.data.srcset,
                    }),
                ),
            );
            at += 1;
        } catch (e) {
            console.error('Не удалось загрузить изображение:', e);
        }
    }
}

/**
 * Классический WYSIWYG-редактор (Tiptap) для тела новости: форматирование,
 * заголовки, списки, цитата, ссылки, картинки из медиатеки, таблицы и
 * выравнивание. Отдаёт HTML через onChange. Мультиязычность обеспечивает
 * родитель, перемонтируя редактор по `key={locale}`.
 */
export function RichEditor({ value, onChange, placeholder }: Props) {
    const [pickerOpen, setPickerOpen] = useState(false);
    const [colorOpen, setColorOpen] = useState(false);
    // Перерисовываем тулбар на каждую транзакцию, чтобы активные состояния
    // кнопок были актуальны (Tiptap v3 не ререндерит компонент сам).
    const [, force] = useReducer((n: number) => n + 1, 0);

    const editor = useEditor({
        immediatelyRender: false,
        extensions: [
            StarterKit.configure({
                heading: { levels: [2, 3, 4] },
                link: {
                    openOnClick: false,
                    autolink: true,
                    HTMLAttributes: {
                        rel: 'noopener nofollow',
                        target: '_blank',
                    },
                },
            }),
            RichImage.configure({ inline: false }),
            TextAlign.configure({ types: ['heading', 'paragraph'] }),
            TableKit.configure({ table: { resizable: false } }),
            TextStyle,
            Color,
            Youtube.configure({
                nocookie: true,
                controls: true,
                HTMLAttributes: { class: 're-video' },
            }),
            Placeholder.configure({
                placeholder: placeholder ?? 'Текст новости…',
            }),
        ],
        content: value,
        editorProps: {
            attributes: { class: 're-content' },
            // Перетаскивание картинки в текст: грузим в медиатеку и вставляем.
            handleDrop: (view, event) => {
                const files = Array.from(
                    event.dataTransfer?.files ?? [],
                ).filter((f) => f.type.startsWith('image/'));

                if (files.length === 0) {
                    return false;
                }

                event.preventDefault();
                const coords = view.posAtCoords({
                    left: event.clientX,
                    top: event.clientY,
                });
                void uploadImagesAt(
                    view,
                    files,
                    coords?.pos ?? view.state.selection.from,
                );

                return true;
            },
            // Вставка картинки из буфера обмена.
            handlePaste: (view, event) => {
                const files = Array.from(
                    event.clipboardData?.files ?? [],
                ).filter((f) => f.type.startsWith('image/'));

                if (files.length === 0) {
                    return false;
                }

                event.preventDefault();
                void uploadImagesAt(view, files, view.state.selection.from);

                return true;
            },
        },
        onUpdate: ({ editor }) => onChange(editor.getHTML()),
    });

    useEffect(() => {
        if (!editor) {
            return;
        }

        const update = () => force();
        editor.on('transaction', update);
        editor.on('selectionUpdate', update);

        return () => {
            editor.off('transaction', update);
            editor.off('selectionUpdate', update);
        };
    }, [editor]);

    if (!editor) {
        return <div className="re-shell re-loading">Загрузка редактора…</div>;
    }

    const setLink = () => {
        if (editor.isActive('link')) {
            editor.chain().focus().unsetLink().run();

            return;
        }

        const prev = (editor.getAttributes('link').href as string) ?? '';
        const url = window.prompt('Ссылка (URL):', prev);

        if (url === null) {
            return; // отмена
        }

        if (url === '') {
            editor.chain().focus().unsetLink().run();

            return;
        }

        editor
            .chain()
            .focus()
            .extendMarkRange('link')
            .setLink({ href: url })
            .run();
    };

    const insertImage = (item: MediaItem) => {
        editor
            .chain()
            .focus()
            .insertContent({
                type: 'image',
                attrs: {
                    src: item.url,
                    alt: item.alt ?? item.name ?? '',
                    caption: item.caption,
                    srcset: item.srcset,
                },
            })
            .run();
        setPickerOpen(false);
    };

    const insertVideo = () => {
        const url = window.prompt(
            'Ссылка на видео YouTube (watch, youtu.be или embed):',
            '',
        );

        if (url) {
            editor.commands.setYoutubeVideo({ src: url });
        }
    };

    const inTable = editor.isActive('table');

    const imageActive = editor.isActive('image');
    const imageAttrs = imageActive ? editor.getAttributes('image') : {};
    const setImageAttr = (attrs: Record<string, unknown>) =>
        editor.chain().focus().updateAttributes('image', attrs).run();
    const editCaption = () => {
        const value = window.prompt(
            'Подпись к изображению:',
            (imageAttrs.caption as string) ?? '',
        );

        if (value !== null) {
            setImageAttr({ caption: value.trim() || null });
        }
    };

    return (
        <div className="re-shell">
            <div
                className="re-toolbar"
                role="toolbar"
                aria-label="Форматирование"
            >
                <Btn
                    icon={<Undo2 size={16} />}
                    label="Отменить"
                    disabled={!editor.can().undo()}
                    onClick={() => editor.chain().focus().undo().run()}
                />
                <Btn
                    icon={<Redo2 size={16} />}
                    label="Повторить"
                    disabled={!editor.can().redo()}
                    onClick={() => editor.chain().focus().redo().run()}
                />
                <Sep />

                <Btn
                    icon={<Heading2 size={16} />}
                    label="Заголовок 2"
                    active={editor.isActive('heading', { level: 2 })}
                    onClick={() =>
                        editor.chain().focus().toggleHeading({ level: 2 }).run()
                    }
                />
                <Btn
                    icon={<Heading3 size={16} />}
                    label="Заголовок 3"
                    active={editor.isActive('heading', { level: 3 })}
                    onClick={() =>
                        editor.chain().focus().toggleHeading({ level: 3 }).run()
                    }
                />
                <Btn
                    icon={<Heading4 size={16} />}
                    label="Заголовок 4"
                    active={editor.isActive('heading', { level: 4 })}
                    onClick={() =>
                        editor.chain().focus().toggleHeading({ level: 4 }).run()
                    }
                />
                <Sep />

                <Btn
                    icon={<Bold size={16} />}
                    label="Полужирный"
                    active={editor.isActive('bold')}
                    onClick={() => editor.chain().focus().toggleBold().run()}
                />
                <Btn
                    icon={<Italic size={16} />}
                    label="Курсив"
                    active={editor.isActive('italic')}
                    onClick={() => editor.chain().focus().toggleItalic().run()}
                />
                <Btn
                    icon={<Underline size={16} />}
                    label="Подчёркнутый"
                    active={editor.isActive('underline')}
                    onClick={() =>
                        editor.chain().focus().toggleUnderline().run()
                    }
                />
                <Btn
                    icon={<Strikethrough size={16} />}
                    label="Зачёркнутый"
                    active={editor.isActive('strike')}
                    onClick={() => editor.chain().focus().toggleStrike().run()}
                />

                {/* Цвет текста */}
                <span className="re-color">
                    <button
                        type="button"
                        className={`re-btn${editor.isActive('textStyle') ? 'is-active' : ''}`}
                        title="Цвет текста"
                        aria-label="Цвет текста"
                        onMouseDown={(e) => e.preventDefault()}
                        onClick={() => setColorOpen((v) => !v)}
                    >
                        <Baseline size={16} />
                    </button>
                    {colorOpen && (
                        <div className="re-color-panel">
                            {TEXT_COLORS.map((c) => (
                                <button
                                    key={c.value}
                                    type="button"
                                    className="re-swatch"
                                    title={c.label}
                                    style={{ background: c.value }}
                                    onMouseDown={(e) => e.preventDefault()}
                                    onClick={() => {
                                        editor
                                            .chain()
                                            .focus()
                                            .setColor(c.value)
                                            .run();
                                        setColorOpen(false);
                                    }}
                                />
                            ))}
                            <button
                                type="button"
                                className="re-swatch re-swatch-reset"
                                title="Убрать цвет"
                                onMouseDown={(e) => e.preventDefault()}
                                onClick={() => {
                                    editor.chain().focus().unsetColor().run();
                                    setColorOpen(false);
                                }}
                            >
                                ✕
                            </button>
                        </div>
                    )}
                </span>
                <Sep />

                <Btn
                    icon={<List size={16} />}
                    label="Маркированный список"
                    active={editor.isActive('bulletList')}
                    onClick={() =>
                        editor.chain().focus().toggleBulletList().run()
                    }
                />
                <Btn
                    icon={<ListOrdered size={16} />}
                    label="Нумерованный список"
                    active={editor.isActive('orderedList')}
                    onClick={() =>
                        editor.chain().focus().toggleOrderedList().run()
                    }
                />
                <Btn
                    icon={<Quote size={16} />}
                    label="Цитата"
                    active={editor.isActive('blockquote')}
                    onClick={() =>
                        editor.chain().focus().toggleBlockquote().run()
                    }
                />
                <Btn
                    icon={<Minus size={16} />}
                    label="Разделитель"
                    onClick={() =>
                        editor.chain().focus().setHorizontalRule().run()
                    }
                />
                <Sep />

                <Btn
                    icon={<AlignLeft size={16} />}
                    label="По левому краю"
                    active={editor.isActive({ textAlign: 'left' })}
                    onClick={() =>
                        editor.chain().focus().setTextAlign('left').run()
                    }
                />
                <Btn
                    icon={<AlignCenter size={16} />}
                    label="По центру"
                    active={editor.isActive({ textAlign: 'center' })}
                    onClick={() =>
                        editor.chain().focus().setTextAlign('center').run()
                    }
                />
                <Btn
                    icon={<AlignRight size={16} />}
                    label="По правому краю"
                    active={editor.isActive({ textAlign: 'right' })}
                    onClick={() =>
                        editor.chain().focus().setTextAlign('right').run()
                    }
                />
                <Btn
                    icon={<AlignJustify size={16} />}
                    label="По ширине"
                    active={editor.isActive({ textAlign: 'justify' })}
                    onClick={() =>
                        editor.chain().focus().setTextAlign('justify').run()
                    }
                />
                <Sep />

                <Btn
                    icon={<Link2 size={16} />}
                    label="Ссылка"
                    active={editor.isActive('link')}
                    onClick={setLink}
                />
                {editor.isActive('link') && (
                    <Btn
                        icon={<Link2Off size={16} />}
                        label="Убрать ссылку"
                        onClick={() => editor.chain().focus().unsetLink().run()}
                    />
                )}
                <Btn
                    icon={<ImageIcon size={16} />}
                    label="Изображение из медиатеки"
                    onClick={() => setPickerOpen(true)}
                />
                <Btn
                    icon={<Video size={16} />}
                    label="Видео с YouTube"
                    onClick={insertVideo}
                />
                <Btn
                    icon={<TableIcon size={16} />}
                    label="Вставить таблицу"
                    active={inTable}
                    onClick={() =>
                        editor
                            .chain()
                            .focus()
                            .insertTable({
                                rows: 3,
                                cols: 3,
                                withHeaderRow: true,
                            })
                            .run()
                    }
                />
                {inTable && (
                    <>
                        <Btn
                            icon={<Rows3 size={16} />}
                            label="Добавить строку"
                            onClick={() =>
                                editor.chain().focus().addRowAfter().run()
                            }
                        />
                        <Btn
                            icon={<Columns3 size={16} />}
                            label="Добавить столбец"
                            onClick={() =>
                                editor.chain().focus().addColumnAfter().run()
                            }
                        />
                        <Btn
                            icon={<Trash2 size={16} />}
                            label="Удалить таблицу"
                            onClick={() =>
                                editor.chain().focus().deleteTable().run()
                            }
                        />
                    </>
                )}
                <Sep />

                <Btn
                    icon={<RemoveFormatting size={16} />}
                    label="Очистить форматирование"
                    onClick={() =>
                        editor
                            .chain()
                            .focus()
                            .unsetAllMarks()
                            .clearNodes()
                            .run()
                    }
                />
            </div>

            {imageActive && (
                <div
                    className="re-imagebar"
                    role="toolbar"
                    aria-label="Параметры изображения"
                >
                    <span className="re-imagebar-label">Обтекание:</span>
                    {IMG_ALIGN.map((o) => (
                        <button
                            key={o.label}
                            type="button"
                            className={`re-pill${(imageAttrs.align ?? null) === o.value ? 'is-active' : ''}`}
                            onMouseDown={(e) => e.preventDefault()}
                            onClick={() => setImageAttr({ align: o.value })}
                        >
                            {o.label}
                        </button>
                    ))}
                    <span className="re-sep" aria-hidden />
                    <span className="re-imagebar-label">Размер:</span>
                    {IMG_SIZE.map((o) => (
                        <button
                            key={o.value ?? 'none'}
                            type="button"
                            className={`re-pill${(imageAttrs.size ?? null) === o.value ? 'is-active' : ''}`}
                            onMouseDown={(e) => e.preventDefault()}
                            onClick={() => setImageAttr({ size: o.value })}
                        >
                            {o.label}
                        </button>
                    ))}
                    <span className="re-sep" aria-hidden />
                    <button
                        type="button"
                        className={`re-pill${imageAttrs.caption ? 'is-active' : ''}`}
                        onMouseDown={(e) => e.preventDefault()}
                        onClick={editCaption}
                    >
                        Подпись…
                    </button>
                </div>
            )}

            <EditorContent editor={editor} className="re-content-wrap" />

            <MediaPicker
                open={pickerOpen}
                onClose={() => setPickerOpen(false)}
                onSelect={insertImage}
            />
        </div>
    );
}

export type { Editor };
