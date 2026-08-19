import type { Editor } from '@tiptap/react';
import {
    AlignCenter,
    AlignJustify,
    AlignLeft,
    AlignRight,
    Baseline,
    Bold,
    CodeXml,
    Heading2,
    Heading3,
    Heading4,
    Image as ImageIcon,
    Italic,
    Link2,
    Link2Off,
    List,
    ListOrdered,
    Maximize2,
    Minimize2,
    Minus,
    Quote,
    Redo2,
    RemoveFormatting,
    Strikethrough,
    Table as TableIcon,
    Underline,
    Undo2,
    Video,
} from 'lucide-react';
import type { Dispatch, ReactNode, SetStateAction } from 'react';
import { useEffect, useRef, useState } from 'react';

/** Кнопка тулбара. */
export function Btn({
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
            className={`re-btn${active ? ' is-active' : ''}`}
            title={label}
            aria-label={label}
            aria-pressed={active}
            disabled={disabled}
            onMouseDown={(e) => e.preventDefault()}
            onClick={onClick}
        >
            {icon}
        </button>
    );
}

/** Палитра цветов текста (значения работают и в редакторе, и на сайте). */
const TEXT_COLORS: { label: string; value: string }[] = [
    { label: 'Красный', value: '#b3362a' },
    { label: 'Оранжевый', value: '#b5651d' },
    { label: 'Зелёный', value: '#2e7d46' },
    { label: 'Синий', value: '#416180' },
    { label: 'Серый', value: '#5d5d60' },
];

interface Props {
    editor: Editor;
    setLink: () => void;
    insertVideo: () => void;
    setPickerOpen: Dispatch<SetStateAction<boolean>>;
    focusMode: boolean;
    onToggleFocus: () => void;
    sourceMode: boolean;
    onToggleSource: () => void;
    onInsertTable: () => void;
}

/**
 * Панель форматирования RichEditor — вынесена из основного компонента,
 * т.к. это был самый крупный неразбитый блок в файле (D-6, CMS_AUDIT.md P2).
 * Держит только своё локальное состояние (открытость палитры цветов);
 * всё остальное (сам editor, вставка ссылки/видео, статус таблицы,
 * открытие медиатеки) приходит от родителя, который уже это вычисляет.
 */
export function RichEditorToolbar({
    editor,
    setLink,
    insertVideo,
    setPickerOpen,
    focusMode,
    onToggleFocus,
    sourceMode,
    onToggleSource,
    onInsertTable,
}: Props) {
    const [colorOpen, setColorOpen] = useState(false);
    const colorRef = useRef<HTMLSpanElement>(null);

    useEffect(() => {
        if (!colorOpen) {
            return;
        }

        const close = (event: MouseEvent) => {
            if (
                colorRef.current &&
                !colorRef.current.contains(event.target as Node)
            ) {
                setColorOpen(false);
            }
        };

        document.addEventListener('mousedown', close);

        return () => document.removeEventListener('mousedown', close);
    }, [colorOpen]);

    return (
        <div className="re-toolbar" role="toolbar" aria-label="Форматирование">
            <div className="re-toolbar-cluster" aria-disabled={sourceMode}>
                <div className="re-group">
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
                </div>

                <div className="re-group">
                    <Btn
                        icon={<Heading2 size={16} />}
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
                        icon={<Heading3 size={16} />}
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
                        icon={<Heading4 size={16} />}
                        label="Заголовок 4"
                        active={editor.isActive('heading', { level: 4 })}
                        onClick={() =>
                            editor
                                .chain()
                                .focus()
                                .toggleHeading({ level: 4 })
                                .run()
                        }
                    />
                </div>

                <div className="re-group">
                    <Btn
                        icon={<Bold size={16} />}
                        label="Полужирный"
                        active={editor.isActive('bold')}
                        onClick={() =>
                            editor.chain().focus().toggleBold().run()
                        }
                    />
                    <Btn
                        icon={<Italic size={16} />}
                        label="Курсив"
                        active={editor.isActive('italic')}
                        onClick={() =>
                            editor.chain().focus().toggleItalic().run()
                        }
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
                        onClick={() =>
                            editor.chain().focus().toggleStrike().run()
                        }
                    />
                    <span className="re-color" ref={colorRef}>
                        <button
                            type="button"
                            className={`re-btn${editor.isActive('textStyle') ? ' is-active' : ''}`}
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
                                        editor
                                            .chain()
                                            .focus()
                                            .unsetColor()
                                            .run();
                                        setColorOpen(false);
                                    }}
                                >
                                    ✕
                                </button>
                            </div>
                        )}
                    </span>
                </div>

                <div className="re-group">
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
                </div>

                <div className="re-group">
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
                </div>

                <div className="re-group">
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
                            onClick={() =>
                                editor.chain().focus().unsetLink().run()
                            }
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
                        onClick={onInsertTable}
                    />
                </div>

                <div className="re-group">
                    <Btn
                        icon={<RemoveFormatting size={16} />}
                        label="Очистить форматирование"
                        disabled={sourceMode}
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
            </div>
            <div className="re-toolbar-end">
                <Btn
                    icon={<CodeXml size={16} />}
                    label={sourceMode ? 'Визуальный режим' : 'Исходный HTML'}
                    active={sourceMode}
                    onClick={onToggleSource}
                />
                <Btn
                    icon={
                        focusMode ? (
                            <Minimize2 size={16} />
                        ) : (
                            <Maximize2 size={16} />
                        )
                    }
                    label={
                        focusMode ? 'Выйти из режима письма' : 'На весь экран'
                    }
                    active={focusMode}
                    onClick={onToggleFocus}
                />
            </div>
        </div>
    );
}
