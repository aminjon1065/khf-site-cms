import { Placeholder } from '@tiptap/extension-placeholder';
import { TableKit } from '@tiptap/extension-table';
import { TextAlign } from '@tiptap/extension-text-align';
import { Color, TextStyle } from '@tiptap/extension-text-style';
import { Underline } from '@tiptap/extension-underline';
import { Youtube } from '@tiptap/extension-youtube';
import type { EditorView } from '@tiptap/pm/view';
import { EditorContent, useEditor, useEditorState } from '@tiptap/react';
import type { Editor } from '@tiptap/react';
import { BubbleMenu } from '@tiptap/react/menus';
import { StarterKit } from '@tiptap/starter-kit';
import { Bold, Heading2, Heading3, Italic, Link2, Trash2 } from 'lucide-react';
import { useEffect, useState } from 'react';
import MediaController from '@/actions/App/Http/Controllers/Cms/MediaController';
import { postForm } from '@/lib/http';
import { cn } from '@/lib/utils';
import { MediaPicker } from './MediaPicker';
import type { MediaItem } from './MediaPicker';
import {
    cleanPastedHtml,
    countWords,
    htmlHasYoutube,
    readingMinutes,
} from './rich-editor';
import { RichImage } from './rich-image';
import {
    deleteLastTable,
    documentHasTable,
    lastTableRange,
    RichTableCell,
    RichTableHeader,
    selectInsideLastTable,
} from './rich-table';
import { LinkDialog, YoutubeDialog } from './RichEditorDialogs';
import type { LinkDialogValue } from './RichEditorDialogs';
import { Btn, RichEditorToolbar } from './RichEditorToolbar';
import { RichTableBar } from './RichTableBar';
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

function insertBlock(
    editor: Editor,
    content: Record<string, unknown>,
): boolean {
    editor.view.focus();
    const from = editor.state.selection.from;

    if (editor.chain().focus().insertContentAt(from, content).run()) {
        return true;
    }

    return editor
        .chain()
        .focus()
        .insertContentAt(editor.state.doc.content.size - 1, content)
        .run();
}

function lastNodeRange(
    editor: Editor,
    name: string,
): { pos: number; size: number } | null {
    let last: { pos: number; size: number } | null = null;

    editor.state.doc.descendants((node, pos) => {
        if (node.type.name === name) {
            last = { pos, size: node.nodeSize };
        }
    });

    return last;
}

function deleteNamedNode(editor: Editor, name: string): boolean {
    if (editor.isActive(name)) {
        return editor.chain().focus().deleteSelection().run();
    }

    const range = lastNodeRange(editor, name);

    if (!range) {
        return false;
    }

    return editor
        .chain()
        .focus()
        .deleteRange({ from: range.pos, to: range.pos + range.size })
        .run();
}

function editorSnapshot(editor: Editor | null): {
    hasTable: boolean;
    hasYoutube: boolean;
    words: number;
    chars: number;
} {
    if (!editor) {
        return {
            hasTable: false,
            hasYoutube: false,
            words: 0,
            chars: 0,
        };
    }

    const html = editor.getHTML();
    const text = editor.getText();

    return {
        hasTable: documentHasTable(editor),
        hasYoutube: htmlHasYoutube(html) || editor.isActive('youtube'),
        words: countWords(text),
        chars: text.length,
    };
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
    const [dragging, setDragging] = useState(false);
    const [focused, setFocused] = useState(false);
    const [tableBarOpen, setTableBarOpen] = useState(false);
    const [videoBarOpen, setVideoBarOpen] = useState(false);

    const editor = useEditor({
        immediatelyRender: false,
        shouldRerenderOnTransaction: true,
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
            TableKit.configure({
                table: {
                    resizable: true,
                    lastColumnResizable: true,
                    allowTableNodeSelection: true,
                },
                tableCell: false,
                tableHeader: false,
            }),
            RichTableCell,
            RichTableHeader,
            TextStyle,
            Underline,
            Color,
            Youtube.configure({
                nocookie: true,
                controls: true,
                HTMLAttributes: { class: 're-video' },
            }),
            Placeholder.configure({
                placeholder:
                    placeholder ??
                    'Начните писать текст. Выделите фразу — появится панель форматирования.',
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
        onFocus: () => setFocused(true),
        onBlur: () => setFocused(false),
    });

    const snapshot =
        useEditorState({
            editor,
            selector: ({ editor: instance }) => editorSnapshot(instance),
        }) ?? editorSnapshot(editor);

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
        return <EditorSkeleton />;
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
        const attrs = imageAttrsFromMedia(item);
        editor.view.focus();
        const inserted =
            editor.chain().focus().setImage(attrs).run() ||
            insertBlock(editor, { type: 'image', attrs });

        if (inserted) {
            selectImageNearCursor(editor);
        } else {
            toast('Не удалось вставить изображение в текст', 'error');
        }

        setPickerOpen(false);
    };

    const insertTable = () => {
        editor.view.focus();
        const inserted = editor
            .chain()
            .focus()
            .insertTable({ rows: 3, cols: 3, withHeaderRow: true })
            .run();

        if (!inserted && lastTableRange(editor) === null) {
            toast('Не удалось вставить таблицу', 'error');

            return;
        }

        selectInsideLastTable(editor);
        setTableBarOpen(true);
    };

    const insertYoutube = (src: string) => {
        editor.view.focus();
        const inserted =
            editor.chain().focus().setYoutubeVideo({ src }).run() ||
            insertBlock(editor, { type: 'youtube', attrs: { src } });

        if (!inserted) {
            toast(
                'Не удалось вставить видео. Проверьте ссылку YouTube.',
                'error',
            );
        } else {
            setVideoBarOpen(true);
        }

        setVideoOpen(false);
    };

    const words = snapshot.words;
    const chars = snapshot.chars;
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
                focused && 'is-focused',
                dragging && 'is-dragging',
            )}
            onDragEnter={(event) => {
                if (Array.from(event.dataTransfer.types).includes('Files')) {
                    setDragging(true);
                }
            }}
            onDragOver={(event) => {
                if (dragging) {
                    event.preventDefault();
                }
            }}
            onDragLeave={(event) => {
                if (
                    !event.currentTarget.contains(event.relatedTarget as Node)
                ) {
                    setDragging(false);
                }
            }}
            onDrop={() => setDragging(false)}
        >
            <RichEditorToolbar
                editor={editor}
                setLink={() => setLinkOpen(true)}
                insertVideo={() => setVideoOpen(true)}
                setPickerOpen={setPickerOpen}
                focusMode={focusMode}
                onToggleFocus={() => setFocusMode((open) => !open)}
                sourceMode={sourceMode}
                onToggleSource={toggleSource}
                onInsertTable={insertTable}
            />

            {(snapshot.hasTable || tableBarOpen) && (
                <RichTableBar
                    editor={editor}
                    onDeleteTable={() => {
                        if (!deleteLastTable(editor)) {
                            toast('Не удалось удалить таблицу', 'error');

                            return;
                        }

                        setTableBarOpen(false);
                    }}
                />
            )}

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

            {(snapshot.hasYoutube || videoBarOpen) && (
                <div className="re-table-bar" role="toolbar" aria-label="Видео">
                    <strong>Видео</strong>
                    <Btn
                        icon={<Trash2 size={15} />}
                        label="Удалить видео"
                        onClick={() => {
                            if (!deleteNamedNode(editor, 'youtube')) {
                                toast('Не удалось удалить видео', 'error');

                                return;
                            }

                            setVideoBarOpen(false);
                        }}
                    />
                </div>
            )}
            {dragging && (
                <div className="re-drop" aria-hidden>
                    Перетащите изображение в текст
                </div>
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
                <span className="re-status-hint">
                    {focusMode
                        ? 'Esc — выйти из режима письма'
                        : 'Перетащите фото в текст или вставьте из буфера'}
                </span>
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
                onApply={insertYoutube}
            />
        </div>
    );
}

function EditorSkeleton() {
    return (
        <div
            className="re-shell re-loading"
            aria-busy="true"
            aria-label="Загрузка редактора"
        >
            <div className="re-toolbar">
                <span className="re-skel re-skel-bar" />
            </div>
            <div className="re-skel-body">
                <span className="re-skel re-skel-line" />
                <span className="re-skel re-skel-line is-short" />
                <span className="re-skel re-skel-line is-mid" />
            </div>
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
