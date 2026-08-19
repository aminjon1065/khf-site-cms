import { TableCell, TableHeader } from '@tiptap/extension-table';
import { TextSelection } from '@tiptap/pm/state';
import type { Editor } from '@tiptap/react';
import { htmlHasTable, parseCssColor, stripLastTable } from './rich-editor';

function isTableNode(node: {
    type: { name: string; spec: { tableRole?: string } };
}): boolean {
    return node.type.spec.tableRole === 'table' || node.type.name === 'table';
}

export function lastTableRange(
    editor: Editor,
): { pos: number; size: number } | null {
    let last: { pos: number; size: number } | null = null;

    editor.state.doc.descendants((node, pos) => {
        if (isTableNode(node)) {
            last = { pos, size: node.nodeSize };
        }
    });

    return last;
}

export function selectInsideLastTable(editor: Editor): boolean {
    const table = lastTableRange(editor);

    if (table === null) {
        return false;
    }

    const selection = TextSelection.near(
        editor.state.doc.resolve(table.pos + 1),
    );
    editor.view.dispatch(editor.state.tr.setSelection(selection));
    editor.view.focus();

    return true;
}

export function selectionInTable(editor: Editor): boolean {
    const $from = editor.state.selection.$from;

    for (let depth = $from.depth; depth > 0; depth -= 1) {
        const node = $from.node(depth);

        if (
            node.type.spec.tableRole ||
            node.type.name === 'table' ||
            node.type.name === 'tableRow' ||
            node.type.name === 'tableCell' ||
            node.type.name === 'tableHeader'
        ) {
            return true;
        }
    }

    return false;
}

export function documentHasTable(editor: Editor): boolean {
    if (lastTableRange(editor) !== null) {
        return true;
    }

    if (htmlHasTable(editor.getHTML())) {
        return true;
    }

    return Boolean(editor.view.dom.querySelector?.('table'));
}

export function runTableCommand(
    editor: Editor,
    command: () => boolean,
): boolean {
    if (!selectionInTable(editor)) {
        selectInsideLastTable(editor);
    }

    return command();
}

export function deleteLastTable(editor: Editor): boolean {
    if (
        selectInsideLastTable(editor) &&
        editor.chain().focus().deleteTable().run() &&
        !documentHasTable(editor)
    ) {
        return true;
    }

    const range = lastTableRange(editor);

    if (
        range &&
        editor
            .chain()
            .focus()
            .deleteRange({ from: range.pos, to: range.pos + range.size })
            .run()
    ) {
        return true;
    }

    const html = editor.getHTML();

    if (!htmlHasTable(html)) {
        return false;
    }

    return editor.commands.setContent(stripLastTable(html), {
        emitUpdate: true,
    });
}

const cellBackground = {
    default: null as string | null,
    parseHTML: (element: HTMLElement) =>
        parseCssColor(element.style.backgroundColor),
    renderHTML: (attributes: { backgroundColor?: string | null }) => {
        if (!attributes.backgroundColor) {
            return {};
        }

        return { style: `background-color: ${attributes.backgroundColor}` };
    },
};

/** Ячейка с заливкой — цвет пишется в style, чтобы дожить до публикации. */
export const RichTableCell = TableCell.extend({
    addAttributes() {
        return {
            ...this.parent?.(),
            backgroundColor: cellBackground,
        };
    },
});

export const RichTableHeader = TableHeader.extend({
    addAttributes() {
        return {
            ...this.parent?.(),
            backgroundColor: cellBackground,
        };
    },
});
