import { Head, Link, router } from '@inertiajs/react';
import {
    CornerDownRight,
    MoreVertical,
    Pencil,
    Plus,
    Trash2,
} from 'lucide-react';
import { useState } from 'react';
import StructureUnitController from '@/actions/App/Http/Controllers/Cms/StructureUnitController';
import { useCan } from '@/lib/auth';
import { Tag } from '@/ui/Badge';
import { IconButton, LinkButton } from '@/ui/Button';
import { DataTable } from '@/ui/DataTable';
import type { Column } from '@/ui/DataTable';
import { ConfirmDialog, Dropdown } from '@/ui/Overlay';
import type { MenuItem } from '@/ui/Overlay';
import { PageHeader } from '@/ui/PageHeader';

/** A unit in tree order: each subunit follows its parent, one `depth` deeper. */
interface StructureUnitRow {
    id: number;
    parent_id: number | null;
    depth: number;
    num: string;
    name: string;
    sort: number;
    children_count: number;
}

interface Props {
    units: StructureUnitRow[];
}

export default function StructureIndex({ units }: Props) {
    const can = useCan();
    const editable = can('structure.edit');
    const [deleteTarget, setDeleteTarget] = useState<StructureUnitRow | null>(
        null,
    );
    const [processing, setProcessing] = useState(false);

    const actionsFor = (u: StructureUnitRow): MenuItem[] => [
        ...(can('structure.create')
            ? [
                  {
                      label: 'Добавить вложенное',
                      icon: <Plus size={15} strokeWidth={1.5} />,
                      onSelect: () =>
                          router.visit(
                              StructureUnitController.create.url({
                                  query: { parent: u.id },
                              }),
                          ),
                  },
              ]
            : []),
        {
            label: 'Редактировать',
            icon: <Pencil size={15} strokeWidth={1.5} />,
            onSelect: () =>
                router.visit(StructureUnitController.edit.url(u.id)),
        },
        // A unit with subunits cannot be deleted until they are moved or
        // deleted — the server refuses it too.
        ...(can('structure.delete') && u.children_count === 0
            ? [
                  { separator: true },
                  {
                      label: 'Удалить…',
                      icon: <Trash2 size={15} strokeWidth={1.5} />,
                      danger: true,
                      onSelect: () => setDeleteTarget(u),
                  },
              ]
            : []),
    ];

    const columns: Column<StructureUnitRow>[] = [
        {
            key: 'num',
            header: '№',
            width: 90,
            render: (u) => <span className="ui-mono">{u.num}</span>,
        },
        {
            key: 'name',
            header: 'Подразделение',
            render: (u) => (
                <span
                    style={{
                        display: 'inline-flex',
                        alignItems: 'center',
                        gap: 8,
                        paddingLeft: u.depth * 22,
                    }}
                >
                    {u.depth > 0 && (
                        <CornerDownRight
                            size={14}
                            strokeWidth={1.5}
                            aria-hidden="true"
                            style={{
                                flex: 'none',
                                color: 'var(--color-neutral-400)',
                            }}
                        />
                    )}
                    {editable ? (
                        <Link
                            href={StructureUnitController.edit.url(u.id)}
                            style={{
                                fontWeight: u.depth === 0 ? 600 : 500,
                                color: 'var(--color-text)',
                                textDecoration: 'none',
                            }}
                        >
                            {u.name}
                        </Link>
                    ) : (
                        <span style={{ fontWeight: u.depth === 0 ? 600 : 500 }}>
                            {u.name}
                        </span>
                    )}
                    {u.children_count > 0 && (
                        <Tag>в составе: {u.children_count}</Tag>
                    )}
                </span>
            ),
        },
        {
            key: 'sort',
            header: 'Порядок',
            width: 90,
            render: (u) => (
                <span
                    style={{
                        fontSize: 12.5,
                        color: 'var(--color-neutral-700)',
                    }}
                >
                    {u.sort}
                </span>
            ),
        },
        {
            key: 'actions',
            header: '',
            width: 44,
            align: 'right',
            render: (u) =>
                editable ? (
                    <Dropdown
                        align="right"
                        trigger={({ toggle }) => (
                            <IconButton
                                label="Действия"
                                onClick={toggle}
                                variant="ghost"
                            >
                                <MoreVertical size={17} strokeWidth={1.5} />
                            </IconButton>
                        )}
                        items={actionsFor(u)}
                    />
                ) : null,
        },
    ];

    return (
        <>
            <Head title="Структура" />
            <PageHeader
                title="Структура"
                subtitle="Подразделения Комитета с вложенными подразделениями — состав страницы «Структура» на сайте. Порядок задаётся среди подразделений одного уровня."
                actions={
                    can('structure.create') && (
                        <LinkButton
                            href={StructureUnitController.create.url()}
                            variant="primary"
                            icon={<Plus size={16} strokeWidth={2} />}
                        >
                            Добавить
                        </LinkButton>
                    )
                }
            />

            <DataTable
                columns={columns}
                rows={units}
                rowKey={(u) => u.id}
                emptyTitle="Подразделения ещё не добавлены"
                emptyHint="Добавьте первое подразделение."
                emptyAction={
                    can('structure.create') ? (
                        <LinkButton
                            href={StructureUnitController.create.url()}
                            variant="secondary"
                        >
                            Добавить подразделение
                        </LinkButton>
                    ) : undefined
                }
            />

            <ConfirmDialog
                open={!!deleteTarget}
                onClose={() => setDeleteTarget(null)}
                loading={processing}
                title="Удалить подразделение?"
                body={
                    deleteTarget
                        ? `Подразделение «${deleteTarget.name}» будет удалено со страницы «Структура».`
                        : ''
                }
                confirmLabel="Удалить"
                onConfirm={() => {
                    if (!deleteTarget) {
                        return;
                    }

                    setProcessing(true);
                    router.delete(
                        StructureUnitController.destroy.url(deleteTarget.id),
                        {
                            preserveScroll: true,
                            onFinish: () => {
                                setProcessing(false);
                                setDeleteTarget(null);
                            },
                        },
                    );
                }}
            />
        </>
    );
}
