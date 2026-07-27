import type { Editor } from '@tiptap/react';
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
import type { Dispatch, ReactNode, SetStateAction } from 'react';
import { useState } from 'react';

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

interface Props {
    editor: Editor;
    setLink: () => void;
    insertVideo: () => void;
    inTable: boolean;
    setPickerOpen: Dispatch<SetStateAction<boolean>>;
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
    inTable,
    setPickerOpen,
}: Props) {
    const [colorOpen, setColorOpen] = useState(false);

    return (
        <div className="re-toolbar" role="toolbar" aria-label="Форматирование">
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
                onClick={() => editor.chain().focus().toggleUnderline().run()}
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
                onClick={() => editor.chain().focus().toggleBulletList().run()}
            />
            <Btn
                icon={<ListOrdered size={16} />}
                label="Нумерованный список"
                active={editor.isActive('orderedList')}
                onClick={() => editor.chain().focus().toggleOrderedList().run()}
            />
            <Btn
                icon={<Quote size={16} />}
                label="Цитата"
                active={editor.isActive('blockquote')}
                onClick={() => editor.chain().focus().toggleBlockquote().run()}
            />
            <Btn
                icon={<Minus size={16} />}
                label="Разделитель"
                onClick={() => editor.chain().focus().setHorizontalRule().run()}
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
                        .insertTable({ rows: 3, cols: 3, withHeaderRow: true })
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
                    editor.chain().focus().unsetAllMarks().clearNodes().run()
                }
            />
        </div>
    );
}
