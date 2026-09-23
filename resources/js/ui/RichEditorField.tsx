import { Placeholder } from '@tiptap/extension-placeholder';
import { TableKit } from '@tiptap/extension-table';
import { TextAlign } from '@tiptap/extension-text-align';
import { Color, TextStyle } from '@tiptap/extension-text-style';
import { Underline } from '@tiptap/extension-underline';
import { Youtube } from '@tiptap/extension-youtube';
import type { EditorView } from '@tiptap/pm/view';
import { EditorContent, useEditor } from '@tiptap/react';
import type { Editor } from '@tiptap/react';
import { BubbleMenu, FloatingMenu } from '@tiptap/react/menus';
import { StarterKit } from '@tiptap/starter-kit';
import {
    Bold,
    Heading2,
    Heading3,
    Italic,
    Link2,
    Plus,
    Trash2,
} from 'lucide-react';
import { useEffect, useState } from 'react';
import MediaController from '@/actions/App/Http/Controllers/Cms/MediaController';
import { postForm } from '@/lib/http';
import { plural } from '@/lib/plural';
import { cn } from '@/lib/utils';
import { MediaPicker } from './MediaPicker';
import type { MediaItem } from './MediaPicker';
import { RichCallout } from './rich-callout';
import {
    cleanPastedHtml,
    countWords,
    htmlHasYoutube,
    readingMinutes,
} from './rich-editor';
import { RichGallery, registerGalleryPickerHandler } from './rich-gallery';
import { RichGalleryContext } from './rich-gallery-context';
import type { RichGalleryContextValue } from './rich-gallery-context';
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
import { SlashCommandMenu } from './SlashCommandMenu';
import { useToast } from './Toast';

export interface ActiveBlockInfo {
    type:
        | 'image'
        | 'callout'
        | 'table'
        | 'youtube'
        | 'heading'
        | 'blockquote'
        | 'paragraph';
    attrs?: Record<string, unknown>;
}

export interface Props {
    value: string;
    onChange: (html: string) => void;
    placeholder?: string;
    /** Высокое полотно без внутренней прокрутки — для новостей. */
    variant?: 'default' | 'article';
    /** Показать кнопку «Фотогалерея»: вставка маркера карусели в текст. */
    gallery?: boolean;
    /** Клик по чипу галереи в тексте: открыть выбор кадров (медиатека). */
    onGalleryClick?: () => void;
    /** Данные и обработчики фотогалереи материала для интерактивного блока в тексте */
    galleryContext?: RichGalleryContextValue;
    /** Уведомление об активном блоке для инспектора сайдбара */
    onActiveBlockChange?: (block: ActiveBlockInfo | null) => void;
}

/**
 * Атрибуты картинки для вставки в документ. `src` объявлен явно: setImage()
 * требует его наличия, а Record<string, unknown> этого не гарантировал.
 */
function imageAttrsFromMedia(item: MediaItem): {
    src: string;
    alt: string;
    caption: string;
    srcset: string | null;
    mediaId: number;
    align: string;
    size: string;
} {
    return {
        // Относительный путь, если есть: абсолютный URL в сохранённом
        // HTML ломается при смене хоста/порта окружения.
        src: item.path ?? item.url,
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
    gallery = false,
    onGalleryClick,
    galleryContext,
    onActiveBlockChange,
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
    const [slashOpen, setSlashOpen] = useState(false);
    const [slashPosition, setSlashPosition] = useState<{
        top: number;
        left: number;
    } | null>(null);
    const [slashQuery, setSlashQuery] = useState('');

    // Колбэк чипа галереи — через модульный сеттер, не через опции
    // расширения: колбэк в configure() ломал сравнение опций useEditor
    // (React #185, краш редактора по клику в текст).
    useEffect(() => {
        registerGalleryPickerHandler(
            onGalleryClick ?? galleryContext?.onOpenPicker ?? null,
        );

        return () => registerGalleryPickerHandler(null);
    }, [onGalleryClick, galleryContext?.onOpenPicker]);

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
            RichGallery,
            RichCallout,
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
                // An editable region needs a role for its name to be read.
                role: 'textbox',
                'aria-multiline': 'true',
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
        onSelectionUpdate: ({ editor: instance }) => {
            if (!onActiveBlockChange) {
                return;
            }

            if (instance.isActive('image')) {
                onActiveBlockChange({
                    type: 'image',
                    attrs: instance.getAttributes('image'),
                });
            } else if (instance.isActive('callout')) {
                onActiveBlockChange({
                    type: 'callout',
                    attrs: instance.getAttributes('callout'),
                });
            } else if (instance.isActive('table')) {
                onActiveBlockChange({
                    type: 'table',
                    attrs: {},
                });
            } else if (instance.isActive('youtube')) {
                onActiveBlockChange({
                    type: 'youtube',
                    attrs: instance.getAttributes('youtube'),
                });
            } else if (instance.isActive('heading')) {
                onActiveBlockChange({
                    type: 'heading',
                    attrs: instance.getAttributes('heading'),
                });
            } else if (instance.isActive('blockquote')) {
                onActiveBlockChange({
                    type: 'blockquote',
                    attrs: {},
                });
            } else {
                onActiveBlockChange({
                    type: 'paragraph',
                    attrs: {},
                });
            }
        },
        onUpdate: ({ editor: instance }) => {
            onChange(instance.getHTML());

            // Определение слеш-команды
            const { state } = instance;
            const { from } = state.selection;
            const $from = state.doc.resolve(from);
            const textBefore = $from.parent.textBetween(
                0,
                $from.parentOffset,
                undefined,
                ' ',
            );

            if (textBefore.startsWith('/')) {
                try {
                    const coords = instance.view.coordsAtPos(from);
                    setSlashPosition({
                        top: coords.bottom + window.scrollY + 6,
                        left: coords.left + window.scrollX,
                    });
                    setSlashQuery(textBefore.slice(1));
                    setSlashOpen(true);
                } catch {
                    setSlashOpen(false);
                }
            } else if (slashOpen) {
                setSlashOpen(false);
            }
        },
        onFocus: () => setFocused(true),
        onBlur: () => setFocused(false),
    });

    // The editor re-renders this component on every transaction
    // (shouldRerenderOnTransaction), so derive the counters straight from it.
    // A useEditorState selector kept the snapshot taken before the editor
    // existed: an opened 356-word article showed «0 слов».
    const snapshot = editorSnapshot(editor);

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

    const insertCallout = () => {
        editor.view.focus();
        editor.chain().focus().setCallout({ type: 'warning' }).run();
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
                insertCallout={insertCallout}
                insertGallery={
                    gallery
                        ? () => {
                              if (!editor.chain().insertGalleryMarker().run()) {
                                  toast('Не удалось вставить галерею', 'error');
                              }
                          }
                        : undefined
                }
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
                <RichGalleryContext.Provider value={galleryContext ?? null}>
                    <EditorContent
                        editor={editor}
                        className="re-content-wrap"
                    />
                </RichGalleryContext.Provider>
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

            {!sourceMode && (
                <FloatingMenu
                    editor={editor}
                    shouldShow={({ state }) => {
                        const { selection } = state;
                        const { $from, empty } = selection;

                        return (
                            empty &&
                            $from.parent.type.name === 'paragraph' &&
                            $from.parent.content.size === 0
                        );
                    }}
                    className="re-floating-menu"
                >
                    <button
                        type="button"
                        className="re-quick-inserter-btn"
                        title="Добавить блок (/)"
                        aria-label="Добавить блок"
                        onClick={(e) => {
                            e.preventDefault();
                            e.stopPropagation();

                            if (slashOpen) {
                                setSlashOpen(false);

                                return;
                            }

                            const btnRect =
                                e.currentTarget.getBoundingClientRect();
                            setSlashPosition({
                                top: btnRect.bottom + window.scrollY + 6,
                                left: btnRect.left + window.scrollX,
                            });
                            setSlashQuery('');
                            setSlashOpen(true);
                        }}
                    >
                        <Plus size={16} strokeWidth={2.5} />
                    </button>
                </FloatingMenu>
            )}

            <SlashCommandMenu
                editor={editor}
                isOpen={slashOpen}
                onClose={() => setSlashOpen(false)}
                position={slashPosition}
                query={slashQuery}
                onOpenImagePicker={() => setPickerOpen(true)}
                onInsertGallery={
                    gallery
                        ? () => {
                              if (!editor.chain().insertGalleryMarker().run()) {
                                  toast('Не удалось вставить галерею', 'error');
                              }
                          }
                        : () => {}
                }
                onOpenVideoDialog={() => setVideoOpen(true)}
                onInsertTable={insertTable}
            />

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
                        : 'Перетащите фото в текст или вставьте скопированное (Ctrl+V)'}
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
            role="status"
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

export type { Editor };
