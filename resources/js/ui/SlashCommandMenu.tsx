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
import {
    useCallback,
    useEffect,
    useLayoutEffect,
    useMemo,
    useRef,
    useState,
} from 'react';
import { createPortal } from 'react-dom';

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
    const listRef = useRef<HTMLDivElement>(null);

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
                title: 'Крупный подзаголовок',
                description: 'Название раздела текста',
                category: 'text',
                icon: Heading2,
                keywords: ['h2', 'заголовок', 'раздел', 'heading'],
                action: (ed) =>
                    ed.chain().focus().toggleHeading({ level: 2 }).run(),
            },
            {
                id: 'h3',
                title: 'Мелкий подзаголовок',
                description: 'Часть внутри раздела',
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
                keywords: [
                    'галерея',
                    'фотогалерея',
                    'карусель',
                    'снимки',
                    'фото',
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

    // Автоматическая прокрутка выбранного элемента в видимую область списка
    useEffect(() => {
        if (!listRef.current) {
            return;
        }

        const selectedEl = listRef.current.querySelector(
            '.re-slash-item.is-selected',
        ) as HTMLElement | null;

        if (selectedEl) {
            selectedEl.scrollIntoView({ block: 'nearest' });
        }
    }, [selectedIndex]);

    // Закрытие меню при клике вне него
    useEffect(() => {
        if (!isOpen) {
            return;
        }

        const handlePointerDown = (e: MouseEvent | TouchEvent) => {
            if (
                menuRef.current &&
                !menuRef.current.contains(e.target as Node)
            ) {
                onClose();
            }
        };

        document.addEventListener('pointerdown', handlePointerDown);

        return () => {
            document.removeEventListener('pointerdown', handlePointerDown);
        };
    }, [isOpen, onClose]);

    // Вычисление точной позиции с автопереворотом наверх и проверкой границ экрана
    const [adjustedPos, setAdjustedPos] = useState<{
        top: number;
        left: number;
    }>({
        top: position?.top ?? 0,
        left: position?.left ?? 0,
    });

    useLayoutEffect(() => {
        if (!isOpen || !position) {
            return;
        }

        const menuEl = menuRef.current;
        const menuHeight = menuEl ? menuEl.offsetHeight : 340;
        const menuWidth = menuEl ? menuEl.offsetWidth : 320;

        const viewportTop = position.top - window.scrollY;
        const viewportLeft = position.left - window.scrollX;

        let finalTop = position.top;
        let finalLeft = position.left;

        // Если снизу меню не помещается, а сверху места достаточно — переворачиваем наверх
        if (
            viewportTop + menuHeight > window.innerHeight - 16 &&
            viewportTop > menuHeight + 40
        ) {
            finalTop = position.top - menuHeight - 36;
        }

        // Ограничиваем по правому краю вьюпорта
        if (viewportLeft + menuWidth > window.innerWidth - 16) {
            finalLeft = Math.max(
                window.scrollX + 16,
                window.scrollX + window.innerWidth - menuWidth - 16,
            );
        }

        // Ограничиваем по левому краю вьюпорта
        if (finalLeft < window.scrollX + 16) {
            finalLeft = window.scrollX + 16;
        }

        setAdjustedPos({ top: finalTop, left: finalLeft });
    }, [isOpen, position, filtered.length]);

    useEffect(() => {
        if (!isOpen) {
            return;
        }

        const handleKeyDown = (e: KeyboardEvent) => {
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                setSelectedIndex(
                    (prev) => (prev + 1) % Math.max(1, filtered.length),
                );
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                setSelectedIndex(
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
    }, [executeCommand, filtered, isOpen, onClose, selectedIndex]);

    if (
        !isOpen ||
        !position ||
        filtered.length === 0 ||
        typeof document === 'undefined'
    ) {
        return null;
    }

    return createPortal(
        <div
            ref={menuRef}
            className="re-slash-menu"
            style={{
                top: `${adjustedPos.top}px`,
                left: `${adjustedPos.left}px`,
            }}
            role="menu"
            aria-label="Вставка блока"
        >
            <div className="re-slash-menu-header">
                <Search size={14} className="re-slash-search-icon" />
                <span className="re-slash-menu-hint">
                    {query
                        ? `Поиск: «${query}»`
                        : 'Выберите блок или нажмите Esc'}
                </span>
            </div>
            <div ref={listRef} className="re-slash-menu-list">
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
        </div>,
        document.body,
    );
}
