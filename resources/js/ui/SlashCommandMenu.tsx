import type { Editor } from '@tiptap/react';
import {
    AlertTriangle,
    Heading2,
    Heading3,
    Image as ImageIcon,
    Images,
    List,
    ListOrdered,
    Minus,
    Pilcrow,
    Quote,
    Search,
    Table as TableIcon,
    Video,
} from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';

export interface CommandItem {
    id: string;
    title: string;
    description: string;
    category: 'text' | 'media' | 'blocks';
    icon: typeof Heading2;
    keywords: string[];
    action: (editor: Editor) => void;
}

interface Props {
    editor: Editor;
    isOpen: boolean;
    onClose: () => void;
    position: { top: number; left: number } | null;
    query: string;
    onOpenImagePicker: () => void;
    onInsertGallery: () => void;
    onOpenVideoDialog: () => void;
    onInsertTable: () => void;
}

export function SlashCommandMenu({
    editor,
    isOpen,
    onClose,
    position,
    query,
    onOpenImagePicker,
    onInsertGallery,
    onOpenVideoDialog,
    onInsertTable,
}: Props) {
    const [selectedIndex, setSelectedIndex] = useState(0);
    const [prevQuery, setPrevQuery] = useState(query);

    if (prevQuery !== query) {
        setPrevQuery(query);
        setSelectedIndex(0);
    }

    const menuRef = useRef<HTMLDivElement>(null);

    const commands: CommandItem[] = useMemo(
        () => [
            {
                id: 'p',
                title: 'Обычный текст',
                description: 'Простой абзац текста',
                category: 'text',
                icon: Pilcrow,
                keywords: ['текст', 'параграф', 'абзац', 'p', 'text'],
                action: (ed) => ed.chain().focus().setParagraph().run(),
            },
            {
                id: 'h2',
                title: 'Заголовок H2',
                description: 'Крупный заголовок раздела',
                category: 'text',
                icon: Heading2,
                keywords: ['h2', 'заголовок', 'раздел', 'heading'],
                action: (ed) =>
                    ed.chain().focus().toggleHeading({ level: 2 }).run(),
            },
            {
                id: 'h3',
                title: 'Заголовок H3',
                description: 'Подзаголовок подраздела',
                category: 'text',
                icon: Heading3,
                keywords: ['h3', 'подзаголовок', 'subheading'],
                action: (ed) =>
                    ed.chain().focus().toggleHeading({ level: 3 }).run(),
            },
            {
                id: 'callout',
                title: 'Врезка КЧС / Предупреждение',
                description: 'Штормовое предупреждение или акцентная врезка',
                category: 'blocks',
                icon: AlertTriangle,
                keywords: [
                    'врезка',
                    'предупреждение',
                    'важно',
                    'чс',
                    'внимание',
                    'alert',
                    'callout',
                    'notice',
                ],
                action: (ed) =>
                    ed
                        .chain()
                        .focus()
                        .setCallout({ type: 'warning' })
                        .run(),
                    ed.chain().focus().setCallout({ type: 'warning' }).run(),
            },
            {
                id: 'quote',
                title: 'Цитата',
                description: 'Выделенная цитата официального лица',
                category: 'blocks',
                icon: Quote,
                keywords: ['цитата', 'комментарий', 'quote', 'blockquote'],
                action: (ed) => ed.chain().focus().toggleBlockquote().run(),
            },
            {
                id: 'image',
                title: 'Изображение',
                description: 'Вставка фото из медиатеки или загрузка',
                category: 'media',
                icon: ImageIcon,
                keywords: ['фото', 'картинка', 'снимок', 'изображение', 'image', 'photo'],
                keywords: [
                    'фото',
                    'картинка',
                    'снимок',
                    'изображение',
                    'image',
                    'photo',
                ],
                action: () => onOpenImagePicker(),
            },
            {
                id: 'gallery',
                title: 'Фотогалерея',
                description: 'Интерактивная карусель снимков события',
                category: 'media',
                icon: Images,
                keywords: ['галерея', 'карусель', 'снимки', 'альбом', 'gallery'],
                keywords: [
                    'галерея',
                    'карусель',
                    'снимки',
                    'альбом',
                    'gallery',
                ],
                action: () => onInsertGallery(),
            },
            {
                id: 'video',
                title: 'Видео YouTube',
                description: 'Вставка видеоролика по ссылке',
                category: 'media',
                icon: Video,
                keywords: ['видео', 'youtube', 'ролик', 'video'],
                action: () => onOpenVideoDialog(),
            },
            {
                id: 'bullet-list',
                title: 'Маркированный список',
                description: 'Список с точками',
                category: 'text',
                icon: List,
                keywords: ['список', 'точки', 'маркеры', 'bullet', 'list'],
                action: (ed) => ed.chain().focus().toggleBulletList().run(),
            },
            {
                id: 'ordered-list',
                title: 'Нумерованный список',
                description: 'Список с цифрами',
                category: 'text',
                icon: ListOrdered,
                keywords: ['список', 'цифры', 'номера', 'ordered', 'num'],
                action: (ed) => ed.chain().focus().toggleOrderedList().run(),
            },
            {
                id: 'table',
                title: 'Таблица',
                description: 'Таблица 3×3 с заголовками',
                category: 'blocks',
                icon: TableIcon,
                keywords: ['таблица', 'сетка', 'данные', 'table'],
                action: () => onInsertTable(),
            },
            {
                id: 'divider',
                title: 'Разделитель',
                description: 'Горизонтальная разделительная линия',
                category: 'blocks',
                icon: Minus,
                keywords: ['линия', 'разделитель', 'черта', 'hr', 'divider'],
                action: (ed) => ed.chain().focus().setHorizontalRule().run(),
            },
        ],
        [onOpenImagePicker, onInsertGallery, onOpenVideoDialog, onInsertTable],
    );

    const filtered = useMemo(() => {
        const clean = query.toLowerCase().trim();

        if (!clean) {
            return commands;
        }

        return commands.filter(
            (c) =>
                c.title.toLowerCase().includes(clean) ||
                c.description.toLowerCase().includes(clean) ||
                c.keywords.some((k) => k.includes(clean)),
        );
    }, [commands, query]);

    useEffect(() => {
        setSelectedIndex(0);
    }, [query]);
    const executeCommand = useCallback(
        (item: CommandItem) => {
            // Удаляем слеш или введенный текст запроса перед выполнением команды
            const { state } = editor;
            const { from } = state.selection;
            const $from = state.doc.resolve(from);
            const textBefore = $from.parent.textBetween(
                0,
                $from.parentOffset,
                undefined,
                ' ',
            );

            if (textBefore.startsWith('/')) {
                const deleteFrom = from - textBefore.length;

                editor
                    .chain()
                    .focus()
                    .deleteRange({ from: deleteFrom, to: from })
                    .run();
            }

            item.action(editor);
            onClose();
        },
        [editor, onClose],
    );

    useEffect(() => {
        if (!isOpen) {
            return;
        }

        const handleKeyDown = (e: KeyboardEvent) => {
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                setSelectedIndex((prev) => (prev + 1) % Math.max(1, filtered.length));
                setSelectedIndex(
                    (prev) => (prev + 1) % Math.max(1, filtered.length),
                );
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                setSelectedIndex(
                    (prev) => (prev - 1 + filtered.length) % Math.max(1, filtered.length),
                    (prev) =>
                        (prev - 1 + filtered.length) %
                        Math.max(1, filtered.length),
                );
            } else if (e.key === 'Enter') {
                e.preventDefault();
                const item = filtered[selectedIndex];

                if (item) {
                    executeCommand(item);
                }
            } else if (e.key === 'Escape') {
                e.preventDefault();
                onClose();
            }
        };

        window.addEventListener('keydown', handleKeyDown, true);

        return () => window.removeEventListener('keydown', handleKeyDown, true);
    }, [isOpen, filtered, selectedIndex]);
    }, [executeCommand, filtered, isOpen, onClose, selectedIndex]);

    const executeCommand = (item: CommandItem) => {
        // Удаляем слеш или введенный текст запроса перед выполнением команды
        const { state } = editor;
        const { from } = state.selection;
        const $from = state.doc.resolve(from);
        const textBefore = $from.parent.textBetween(0, $from.parentOffset, undefined, ' ');

        if (textBefore.startsWith('/')) {
            const deleteFrom = from - textBefore.length;
            editor.chain().focus().deleteRange({ from: deleteFrom, to: from }).run();
        }

        item.action(editor);
        onClose();
    };

    if (!isOpen || !position || filtered.length === 0) {
        return null;
    }

    return (
        <div
            ref={menuRef}
            className="re-slash-menu"
            style={{
                top: `${position.top}px`,
                left: `${position.left}px`,
            }}
            role="menu"
            aria-label="Вставка блока"
        >
            <div className="re-slash-menu-header">
                <Search size={14} className="re-slash-search-icon" />
                <span className="re-slash-menu-hint">
                    {query ? `Поиск: «${query}»` : 'Выберите блок или нажмите Esc'}
                    {query
                        ? `Поиск: «${query}»`
                        : 'Выберите блок или нажмите Esc'}
                </span>
            </div>
            <div className="re-slash-menu-list">
                {filtered.map((item, index) => {
                    const Icon = item.icon;
                    const isSelected = index === selectedIndex;

                    return (
                        <button
                            key={item.id}
                            type="button"
                            className={`re-slash-item ${isSelected ? 'is-selected' : ''}`}
                            onClick={() => executeCommand(item)}
                            onMouseEnter={() => setSelectedIndex(index)}
                            role="menuitem"
                        >
                            <span className="re-slash-item-icon">
                                <Icon size={16} strokeWidth={2} />
                            </span>
                            <span className="re-slash-item-text">
                                <strong className="re-slash-item-title">
                                    {item.title}
                                </strong>
                                <span className="re-slash-item-desc">
                                    {item.description}
                                </span>
                            </span>
                        </button>
                    );
                })}
            </div>
        </div>
    );
}

