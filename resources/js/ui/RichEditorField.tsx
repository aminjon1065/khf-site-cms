import { Placeholder } from '@tiptap/extension-placeholder';
import { TableKit } from '@tiptap/extension-table';
import { TextAlign } from '@tiptap/extension-text-align';
import { Color, TextStyle } from '@tiptap/extension-text-style';
import { Youtube } from '@tiptap/extension-youtube';
import type { EditorView } from '@tiptap/pm/view';
import { EditorContent, useEditor } from '@tiptap/react';
import type { Editor } from '@tiptap/react';
import { BubbleMenu } from '@tiptap/react/menus';
import { StarterKit } from '@tiptap/starter-kit';
import { Bold, Heading2, Heading3, Italic, Link2 } from 'lucide-react';
import { useEffect, useReducer, useState } from 'react';
import MediaController from '@/actions/App/Http/Controllers/Cms/MediaController';
import { postForm } from '@/lib/http';
import { cn } from '@/lib/utils';
import { MediaPicker } from './MediaPicker';
import type { MediaItem } from './MediaPicker';
import { cleanPastedHtml, countWords, readingMinutes } from './rich-editor';
import { RichImage } from './rich-image';
import { LinkDialog, YoutubeDialog } from './RichEditorDialogs';
import type { LinkDialogValue } from './RichEditorDialogs';
import { Btn, RichEditorToolbar } from './RichEditorToolbar';
import { useToast } from './Toast';

export interface Props {
    value: string;
    onChange: (html: string) => void;
    placeholder?: string;
    /** Высокое полотно без внутренней прокрутки — для новостей. */
    variant?: 'default' | 'article';
}

function imageAttrsFromMedia(item: MediaItem): Record<string, unknown> {
    return {
        src: item.url,
        alt: item.alt ?? item.name ?? '',
        caption: item.caption ?? '',
        srcset: item.srcset,
        mediaId: item.id,
        align: 'center',
        size: 'large',
    };
}

function selectImageNearCursor(editor: Editor): void {
    const cursor = editor.state.selection.from;
    let nearest: number | null = null;
    let nearestDistance = Number.POSITIVE_INFINITY;

    editor.state.doc.descendants((node, pos) => {
        if (node.type.name !== 'image') {
            return;
        }

        const distance = Math.abs(pos - cursor);

        if (distance < nearestDistance) {
            nearest = pos;
            nearestDistance = distance;
        }
    });

    if (nearest !== null) {
        editor.commands.setNodeSelection(nearest);
    }
}

/**
 * Загружает картинки в медиатеку и вставляет их с позиции `pos`. Работает
 * напрямую через ProseMirror-view (drag-and-drop / вставка из буфера).
 */
async function uploadImagesAt(
    view: EditorView,
    files: File[],
    pos: number,
    onError: (message: string) => void,
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
                    imageType.create(imageAttrsFromMedia(res.data)),
                ),
            );
            at += 1;
        } catch {
            onError(`Не удалось загрузить «${file.name}» в медиатеку`);
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
export function RichEditorField({
    value,
    onChange,
    placeholder,
    variant = 'default',
}: Props) {
    const toast = useToast();
    const [pickerOpen, setPickerOpen] = useState(false);
    const [linkOpen, setLinkOpen] = useState(false);
    const [videoOpen, setVideoOpen] = useState(false);
    const [focusMode, setFocusMode] = useState(false);
    const [sourceMode, setSourceMode] = useState(false);
    const [source, setSource] = useState(value);
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
            attributes: {
                class: 're-content',
                'aria-label': placeholder ?? 'Текст материала',
            },
            transformPastedHTML: cleanPastedHtml,
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
                    (message) => toast(message, 'error'),
                );

                return true;
            },
            handlePaste: (view, event) => {
                const files = Array.from(
                    event.clipboardData?.files ?? [],
                ).filter((f) => f.type.startsWith('image/'));

                if (files.length === 0) {
                    return false;
                }

                event.preventDefault();
                void uploadImagesAt(
                    view,
                    files,
                    view.state.selection.from,
                    (message) => toast(message, 'error'),
                );

                return true;
            },
        },
        onUpdate: ({ editor: instance }) => onChange(instance.getHTML()),
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

    useEffect(() => {
        if (!focusMode) {
            return;
        }

        const previous = document.body.style.overflow;
        document.body.style.overflow = 'hidden';

        const onKey = (event: KeyboardEvent) => {
            if (event.key === 'Escape') {
                setFocusMode(false);
            }
        };

        window.addEventListener('keydown', onKey);

        return () => {
            document.body.style.overflow = previous;
            window.removeEventListener('keydown', onKey);
        };
    }, [focusMode]);

    if (!editor) {
        return <div className="re-shell re-loading">Загрузка редактора…</div>;
    }

    const selectedText = editor.state.doc.textBetween(
        editor.state.selection.from,
        editor.state.selection.to,
        ' ',
    );
    const linkAttrs = editor.getAttributes('link');
    const linkDraft: LinkDialogValue = {
        href: (linkAttrs.href as string) ?? '',
        text: selectedText,
        newTab:
            (linkAttrs.target as string | undefined) !== undefined
                ? linkAttrs.target === '_blank'
                : true,
    };

    const applyLink = (next: LinkDialogValue) => {
        const attrs = {
            href: next.href,
            target: next.newTab ? '_blank' : null,
            rel: next.newTab ? 'noopener nofollow' : 'nofollow',
        };

        if (editor.state.selection.empty) {
            editor
                .chain()
                .focus()
                .insertContent({
                    type: 'text',
                    text: next.text || next.href,
                    marks: [{ type: 'link', attrs }],
                })
                .run();
        } else {
            editor.chain().focus().extendMarkRange('link').setLink(attrs).run();
        }

        setLinkOpen(false);
    };

    const insertImage = (item: MediaItem) => {
        editor
            .chain()
            .focus()
            .insertContent({
                type: 'image',
                attrs: imageAttrsFromMedia(item),
            })
            .run();
        selectImageNearCursor(editor);
        setPickerOpen(false);
    };

    const words = countWords(editor.getText());
    const chars = editor.getText().length;
    const minutes = readingMinutes(words);

    const toggleSource = () => {
        if (sourceMode) {
            editor.commands.setContent(source, { emitUpdate: true });
            setSourceMode(false);
            editor.commands.focus();

            return;
        }

        setSource(editor.getHTML());
        setSourceMode(true);
    };

    return (
        <div
            className={cn(
                're-shell',
                variant === 'article' && 'is-article',
                focusMode && 'is-focus',
                sourceMode && 'is-source',
            )}
        >
            <RichEditorToolbar
                editor={editor}
                setLink={() => setLinkOpen(true)}
                insertVideo={() => setVideoOpen(true)}
                inTable={editor.isActive('table')}
                setPickerOpen={setPickerOpen}
                focusMode={focusMode}
                onToggleFocus={() => setFocusMode((open) => !open)}
                sourceMode={sourceMode}
                onToggleSource={toggleSource}
            />

            {sourceMode ? (
                <textarea
                    className="re-source"
                    value={source}
                    onChange={(e) => {
                        setSource(e.target.value);
                        onChange(e.target.value);
                    }}
                    aria-label="Исходный HTML"
                    spellCheck={false}
                />
            ) : (
                <EditorContent editor={editor} className="re-content-wrap" />
            )}

            {!sourceMode && (
                <BubbleMenu
                    editor={editor}
                    appendTo={() => document.body}
                    shouldShow={({ editor: instance, from, to }) =>
                        from !== to &&
                        !instance.isActive('image') &&
                        !instance.isActive('youtube')
                    }
                    className="re-bubble"
                >
                    <Btn
                        icon={<Bold size={15} />}
                        label="Полужирный"
                        active={editor.isActive('bold')}
                        onClick={() =>
                            editor.chain().focus().toggleBold().run()
                        }
                    />
                    <Btn
                        icon={<Italic size={15} />}
                        label="Курсив"
                        active={editor.isActive('italic')}
                        onClick={() =>
                            editor.chain().focus().toggleItalic().run()
                        }
                    />
                    <Btn
                        icon={<Heading2 size={15} />}
                        label="Заголовок 2"
                        active={editor.isActive('heading', { level: 2 })}
                        onClick={() =>
                            editor
                                .chain()
                                .focus()
                                .toggleHeading({ level: 2 })
                                .run()
                        }
                    />
                    <Btn
                        icon={<Heading3 size={15} />}
                        label="Заголовок 3"
                        active={editor.isActive('heading', { level: 3 })}
                        onClick={() =>
                            editor
                                .chain()
                                .focus()
                                .toggleHeading({ level: 3 })
                                .run()
                        }
                    />
                    <Btn
                        icon={<Link2 size={15} />}
                        label="Ссылка"
                        active={editor.isActive('link')}
                        onClick={() => setLinkOpen(true)}
                    />
                </BubbleMenu>
            )}

            <div className="re-status" aria-live="polite">
                <span>
                    {words} {plural(words, 'слово', 'слова', 'слов')}
                </span>
                <span aria-hidden>·</span>
                <span>{chars} знаков</span>
                <span aria-hidden>·</span>
                <span>
                    {minutes === 0
                        ? 'меньше минуты чтения'
                        : `${minutes} мин чтения`}
                </span>
                {focusMode && (
                    <span className="re-status-hint">Esc — выйти</span>
                )}
            </div>

            <MediaPicker
                open={pickerOpen}
                onClose={() => setPickerOpen(false)}
                onSelect={insertImage}
            />
            <LinkDialog
                open={linkOpen}
                initial={linkDraft}
                onClose={() => setLinkOpen(false)}
                onApply={applyLink}
            />
            <YoutubeDialog
                open={videoOpen}
                onClose={() => setVideoOpen(false)}
                onApply={(src) => {
                    editor.commands.setYoutubeVideo({ src });
                    setVideoOpen(false);
                }}
            />
        </div>
    );
}

function plural(count: number, one: string, few: string, many: string): string {
    const mod10 = count % 10;
    const mod100 = count % 100;

    if (mod10 === 1 && mod100 !== 11) {
        return one;
    }

    if (mod10 >= 2 && mod10 <= 4 && (mod100 < 12 || mod100 > 14)) {
        return few;
    }

    return many;
}

export type { Editor };
