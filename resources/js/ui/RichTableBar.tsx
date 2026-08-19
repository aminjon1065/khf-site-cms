import type { Editor } from '@tiptap/react';
import {
    AlignCenter,
    AlignLeft,
    AlignRight,
    Columns3,
    Heading2,
    PaintBucket,
    Rows3,
    TableCellsMerge,
    TableCellsSplit,
    Trash2,
} from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { TABLE_CELL_FILLS } from './rich-editor';
import { runTableCommand } from './rich-table';
import { Btn } from './RichEditorToolbar';

interface Props {
    editor: Editor;
    onDeleteTable: () => void;
}

/**
 * Панель таблицы в духе Word: строки/столбцы, заливка, объединение,
 * выравнивание в ячейке. Ширину столбцов тянут за вертикальную границу.
 */
export function RichTableBar({ editor, onDeleteTable }: Props) {
    const [fillOpen, setFillOpen] = useState(false);
    const fillRef = useRef<HTMLSpanElement>(null);
    const cellAlign =
        editor.getAttributes('tableCell').align ??
        editor.getAttributes('tableHeader').align ??
        null;
    const cellFill =
        editor.getAttributes('tableCell').backgroundColor ??
        editor.getAttributes('tableHeader').backgroundColor ??
        null;

    useEffect(() => {
        if (!fillOpen) {
            return;
        }

        const close = (event: MouseEvent) => {
            if (
                fillRef.current &&
                !fillRef.current.contains(event.target as Node)
            ) {
                setFillOpen(false);
            }
        };

        document.addEventListener('mousedown', close);

        return () => document.removeEventListener('mousedown', close);
    }, [fillOpen]);

    const run = (command: () => boolean) => {
        runTableCommand(editor, command);
    };

    const paint = (value: string | null) => {
        run(() =>
            editor
                .chain()
                .focus()
                .setCellAttribute('backgroundColor', value)
                .run(),
        );
        setFillOpen(false);
    };

    return (
        <div className="re-table-bar" role="toolbar" aria-label="Таблица">
            <strong>Таблица</strong>

            <span className="re-table-bar-group">
                <Btn
                    icon={<Rows3 size={15} />}
                    label="Строка сверху"
                    onClick={() =>
                        run(() => editor.chain().focus().addRowBefore().run())
                    }
                />
                <Btn
                    icon={<Rows3 size={15} />}
                    label="Строка снизу"
                    onClick={() =>
                        run(() => editor.chain().focus().addRowAfter().run())
                    }
                />
                <Btn
                    icon={<Trash2 size={15} />}
                    label="Удалить строку"
                    onClick={() =>
                        run(() => editor.chain().focus().deleteRow().run())
                    }
                />
            </span>

            <span className="re-table-bar-group">
                <Btn
                    icon={<Columns3 size={15} />}
                    label="Столбец слева"
                    onClick={() =>
                        run(() =>
                            editor.chain().focus().addColumnBefore().run(),
                        )
                    }
                />
                <Btn
                    icon={<Columns3 size={15} />}
                    label="Столбец справа"
                    onClick={() =>
                        run(() => editor.chain().focus().addColumnAfter().run())
                    }
                />
                <Btn
                    icon={<Trash2 size={15} />}
                    label="Удалить столбец"
                    onClick={() =>
                        run(() => editor.chain().focus().deleteColumn().run())
                    }
                />
            </span>

            <span className="re-table-bar-group">
                <Btn
                    icon={<TableCellsMerge size={15} />}
                    label="Объединить ячейки"
                    disabled={!editor.can().mergeCells()}
                    onClick={() =>
                        run(() => editor.chain().focus().mergeCells().run())
                    }
                />
                <Btn
                    icon={<TableCellsSplit size={15} />}
                    label="Разделить ячейку"
                    disabled={!editor.can().splitCell()}
                    onClick={() =>
                        run(() => editor.chain().focus().splitCell().run())
                    }
                />
                <Btn
                    icon={<Heading2 size={15} />}
                    label="Строка заголовка"
                    active={editor.isActive('tableHeader')}
                    onClick={() =>
                        run(() =>
                            editor.chain().focus().toggleHeaderRow().run(),
                        )
                    }
                />
            </span>

            <span className="re-table-bar-group">
                <span className="re-color" ref={fillRef}>
                    <button
                        type="button"
                        className={`re-btn${cellFill ? 'is-active' : ''}`}
                        title="Заливка ячейки"
                        aria-label="Заливка ячейки"
                        aria-expanded={fillOpen}
                        onMouseDown={(event) => event.preventDefault()}
                        onClick={() => setFillOpen((open) => !open)}
                    >
                        <PaintBucket size={15} />
                    </button>
                    {fillOpen && (
                        <div className="re-color-panel" role="listbox">
                            {TABLE_CELL_FILLS.map((fill) => (
                                <button
                                    key={fill.label}
                                    type="button"
                                    className={`re-swatch${fill.value === null ? 're-swatch-reset' : ''}${cellFill === fill.value ? 'is-active' : ''}`}
                                    title={fill.label}
                                    aria-label={fill.label}
                                    style={
                                        fill.value
                                            ? { background: fill.value }
                                            : undefined
                                    }
                                    onMouseDown={(event) =>
                                        event.preventDefault()
                                    }
                                    onClick={() => paint(fill.value)}
                                >
                                    {fill.value === null ? '✕' : null}
                                </button>
                            ))}
                        </div>
                    )}
                </span>
                <Btn
                    icon={<AlignLeft size={15} />}
                    label="Текст в ячейке слева"
                    active={cellAlign === 'left'}
                    onClick={() =>
                        run(() =>
                            editor
                                .chain()
                                .focus()
                                .setCellAttribute('align', 'left')
                                .run(),
                        )
                    }
                />
                <Btn
                    icon={<AlignCenter size={15} />}
                    label="Текст в ячейке по центру"
                    active={cellAlign === 'center'}
                    onClick={() =>
                        run(() =>
                            editor
                                .chain()
                                .focus()
                                .setCellAttribute('align', 'center')
                                .run(),
                        )
                    }
                />
                <Btn
                    icon={<AlignRight size={15} />}
                    label="Текст в ячейке справа"
                    active={cellAlign === 'right'}
                    onClick={() =>
                        run(() =>
                            editor
                                .chain()
                                .focus()
                                .setCellAttribute('align', 'right')
                                .run(),
                        )
                    }
                />
            </span>

            <span className="re-table-bar-group">
                <Btn
                    icon={<Trash2 size={15} />}
                    label="Удалить таблицу"
                    onClick={onDeleteTable}
                />
            </span>

            <span className="re-table-hint">
                Потяните границу столбца, чтобы изменить ширину. Выделите
                несколько ячеек — объедините.
            </span>
        </div>
    );
}
