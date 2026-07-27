import { Placeholder } from '@tiptap/extension-placeholder';
import { TableKit } from '@tiptap/extension-table';
import { TextAlign } from '@tiptap/extension-text-align';
import { Color, TextStyle } from '@tiptap/extension-text-style';
import { Youtube } from '@tiptap/extension-youtube';
import type { EditorView } from '@tiptap/pm/view';
import { EditorContent, useEditor } from '@tiptap/react';
import type { Editor } from '@tiptap/react';
import { StarterKit } from '@tiptap/starter-kit';
import { useEffect, useReducer, useState } from 'react';
import MediaController from '@/actions/App/Http/Controllers/Cms/MediaController';
import { postForm } from '@/lib/http';
import { MediaPicker } from './MediaPicker';
import type { MediaItem } from './MediaPicker';
import { RichImage } from './rich-image';
import type { ImageAlign, ImageSize } from './rich-image';
import { RichEditorToolbar } from './RichEditorToolbar';

export interface Props {
    value: string;
    onChange: (html: string) => void;
    placeholder?: string;
}

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
                MediaController.upload.url(),
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
 *
 * Тяжёлая реализация (Tiptap + расширения) — этот файл всегда загружается
 * лениво через `RichEditor.tsx`, поэтому им можно пользоваться напрямую
 * только оттуда.
 */
export function RichEditorField({ value, onChange, placeholder }: Props) {
    const [pickerOpen, setPickerOpen] = useState(false);
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
            <RichEditorToolbar
                editor={editor}
                setLink={setLink}
                insertVideo={insertVideo}
                inTable={inTable}
                setPickerOpen={setPickerOpen}
            />

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
