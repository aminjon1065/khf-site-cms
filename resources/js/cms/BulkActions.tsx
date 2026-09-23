import { router } from '@inertiajs/react';
import { Globe, Send, Trash2 } from 'lucide-react';
import { useState } from 'react';
import type { ReactNode } from 'react';
import { postJson } from '@/lib/http';
import { bulk } from '@/routes/editorial';
import { Button } from '@/ui/Button';
import { ConfirmDialog, Modal } from '@/ui/Overlay';
import { useToast } from '@/ui/Toast';

export type BulkContentType =
    | 'news'
    | 'pages'
    | 'projects'
    | 'instructions'
    | 'announcements'
    | 'documents';

type BulkAction = 'submit' | 'publish' | 'trash';

interface BulkResult {
    done: number;
    skipped: { id: number; title: string; reason: string }[];
    message: string;
}

const CONFIRMATIONS: Record<
    'publish' | 'trash',
    { title: string; body: string; label: string; danger: boolean }
> = {
    publish: {
        title: 'Опубликовать выбранные материалы',
        body: 'Каждый материал пройдёт ту же проверку, что и при публикации по одному. Что её не пройдёт, останется как было — покажем причину.',
        label: 'Опубликовать',
        danger: false,
    },
    trash: {
        title: 'Переместить в корзину',
        body: 'Опубликованные материалы сразу исчезнут с сайта. Всё можно вернуть из корзины.',
        label: 'В корзину',
        danger: true,
    },
};

/**
 * Bulk actions of a content list, as in WordPress: tick rows, then send them
 * for approval, publish them or move them to the trash. The server applies
 * the checks of the single action to each material and reports what it
 * skipped and why (EditorialBulkController).
 *
 * The choice is dropped whenever the list reloads — another page, a filter
 * or the action's own result — so it never holds rows the user can't see.
 * Dialogs live with the page, not in the selection bar, which closes as soon
 * as the choice is cleared.
 */
export function useBulkActions<T extends { id: number }>({
    type,
    rows,
    rowTitle,
    allowed,
}: {
    type: BulkContentType;
    /** The list as the server sent it. */
    rows: T[];
    rowTitle: (row: T) => string;
    allowed: Record<BulkAction, boolean>;
}): {
    table: {
        selectable: boolean;
        selected: Set<string | number>;
        onSelectedChange: (keys: Set<string | number>) => void;
        selectLabel: (row: T) => string;
        bulkActions: ReactNode;
    };
    dialogs: ReactNode;
} {
    const toast = useToast();
    const [selected, setSelected] = useState<Set<string | number>>(
        () => new Set(),
    );
    const [shownRows, setShownRows] = useState(rows);
    const [confirming, setConfirming] = useState<'publish' | 'trash' | null>(
        null,
    );
    const [running, setRunning] = useState<BulkAction | null>(null);
    const [result, setResult] = useState<BulkResult | null>(null);

    if (shownRows !== rows) {
        setShownRows(rows);
        setSelected(new Set());
    }

    const run = async (action: BulkAction) => {
        setRunning(action);

        try {
            const reply = await postJson<BulkResult>(bulk.url(type), {
                action,
                ids: [...selected].map(Number),
            });

            if (reply.skipped.length === 0) {
                toast(reply.message, 'success');
            } else {
                setResult(reply);
            }

            setSelected(new Set());
            router.reload();
        } catch (error) {
            toast(
                error instanceof Error
                    ? error.message
                    : 'Не удалось выполнить действие.',
                'error',
            );
        } finally {
            setRunning(null);
            setConfirming(null);
        }
    };

    const busy = running !== null;
    const confirmation = confirming ? CONFIRMATIONS[confirming] : null;

    const bulkActions = (
        <>
            {allowed.submit && (
                <Button
                    size="sm"
                    icon={<Send size={14} strokeWidth={1.75} />}
                    loading={running === 'submit'}
                    disabled={busy}
                    onClick={() => run('submit')}
                >
                    На согласование
                </Button>
            )}
            {allowed.publish && (
                <Button
                    size="sm"
                    variant="primary"
                    icon={<Globe size={14} strokeWidth={1.75} />}
                    disabled={busy}
                    onClick={() => setConfirming('publish')}
                >
                    Опубликовать
                </Button>
            )}
            {allowed.trash && (
                <Button
                    size="sm"
                    variant="danger-outline"
                    icon={<Trash2 size={14} strokeWidth={1.75} />}
                    disabled={busy}
                    onClick={() => setConfirming('trash')}
                >
                    В корзину
                </Button>
            )}
            <Button
                size="sm"
                variant="ghost"
                disabled={busy}
                onClick={() => setSelected(new Set())}
            >
                Снять выбор
            </Button>
        </>
    );

    const dialogs = (
        <>
            <ConfirmDialog
                open={confirmation !== null}
                onClose={() => setConfirming(null)}
                loading={busy}
                title={
                    confirmation
                        ? `${confirmation.title} (${selected.size})?`
                        : ''
                }
                body={confirmation?.body}
                confirmLabel={confirmation?.label ?? ''}
                danger={confirmation?.danger ?? false}
                onConfirm={() => {
                    if (confirming) {
                        void run(confirming);
                    }
                }}
            />
            <Modal
                open={result !== null}
                onClose={() => setResult(null)}
                title={result?.message}
                footer={
                    <Button variant="primary" onClick={() => setResult(null)}>
                        Понятно
                    </Button>
                }
            >
                <p style={{ marginBottom: 10 }}>
                    Эти материалы остались как были:
                </p>
                <ul
                    style={{
                        display: 'grid',
                        gap: 8,
                        margin: 0,
                        paddingLeft: 18,
                        maxHeight: 320,
                        overflowY: 'auto',
                    }}
                >
                    {result?.skipped.map((item) => (
                        <li key={item.id}>
                            <strong>{item.title}</strong> — {item.reason}
                        </li>
                    ))}
                </ul>
            </Modal>
        </>
    );

    return {
        table: {
            selectable: allowed.submit || allowed.publish || allowed.trash,
            selected,
            onSelectedChange: setSelected,
            selectLabel: (row) => `Выбрать «${rowTitle(row)}»`,
            bulkActions,
        },
        dialogs,
    };
}
