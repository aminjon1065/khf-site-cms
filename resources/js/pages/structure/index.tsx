import { Head, Link, router } from '@inertiajs/react';
import { MoreVertical, Pencil, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import StructureUnitController from '@/actions/App/Http/Controllers/Cms/StructureUnitController';
import { useCan } from '@/lib/auth';
import { IconButton, LinkButton } from '@/ui/Button';
import { DataTable } from '@/ui/DataTable';
import type { Column } from '@/ui/DataTable';
import { ConfirmDialog, Dropdown } from '@/ui/Overlay';
import { PageHeader } from '@/ui/PageHeader';

interface StructureUnitRow {
    id: number;
    num: string;
    name: string;
    sort: number;
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

    const columns: Column<StructureUnitRow>[] = [
        {
            key: 'num',
            header: '№',
            width: 60,
            render: (u) => <span className="ui-mono">{u.num}</span>,
        },
        {
            key: 'name',
            header: 'Подразделение',
            render: (u) =>
                editable ? (
                    <Link
                        href={StructureUnitController.edit.url(u.id)}
                        style={{
                            fontWeight: 600,
                            color: 'var(--color-text)',
                            textDecoration: 'none',
                        }}
                    >
                        {u.name}
                    </Link>
                ) : (
                    <span style={{ fontWeight: 600 }}>{u.name}</span>
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
                        items={[
                            {
                                label: 'Редактировать',
                                icon: <Pencil size={15} strokeWidth={1.5} />,
                                onSelect: () =>
                                    router.visit(
                                        StructureUnitController.edit.url(u.id),
                                    ),
                            },
                            ...(can('structure.delete')
                                ? [
                                      { separator: true },
                                      {
                                          label: 'Удалить…',
                                          icon: (
                                              <Trash2
                                                  size={15}
                                                  strokeWidth={1.5}
                                              />
                                          ),
                                          danger: true,
                                          onSelect: () => setDeleteTarget(u),
                                      },
                                  ]
                                : []),
                        ]}
                    />
                ) : null,
        },
    ];

    return (
        <>
            <Head title="Структура" />
            <PageHeader
                title="Структура"
                subtitle="Специализированные подразделения — состав страницы «Структура» на сайте"
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
