import { Head, Link, router } from '@inertiajs/react';
import { Crown, MoreVertical, Pencil, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import LeaderController from '@/actions/App/Http/Controllers/Cms/LeaderController';
import { useCan } from '@/lib/auth';
import { Tag } from '@/ui/Badge';
import { IconButton, LinkButton } from '@/ui/Button';
import { DataTable } from '@/ui/DataTable';
import type { Column } from '@/ui/DataTable';
import { ConfirmDialog, Dropdown } from '@/ui/Overlay';
import { PageHeader } from '@/ui/PageHeader';

interface LeaderRow {
    id: number;
    role: string;
    name: string;
    is_chairman: boolean;
    sort: number;
    photo_url: string | null;
}

interface Props {
    leaders: LeaderRow[];
}

export default function LeadershipIndex({ leaders }: Props) {
    const can = useCan();
    const editable = can('leadership.edit');
    const [deleteTarget, setDeleteTarget] = useState<LeaderRow | null>(null);
    const [processing, setProcessing] = useState(false);

    const columns: Column<LeaderRow>[] = [
        {
            key: 'name',
            header: 'ФИО',
            render: (l) => (
                <div style={{ minWidth: 0 }}>
                    {editable ? (
                        <Link
                            href={LeaderController.edit.url(l.id)}
                            style={{
                                fontWeight: 600,
                                color: 'var(--color-text)',
                                textDecoration: 'none',
                            }}
                        >
                            {l.name}
                        </Link>
                    ) : (
                        <span style={{ fontWeight: 600 }}>{l.name}</span>
                    )}
                    <div
                        style={{
                            fontSize: 11.5,
                            color: 'var(--color-neutral-500)',
                        }}
                    >
                        {l.role}
                    </div>
                </div>
            ),
        },
        {
            key: 'status',
            header: '',
            width: 130,
            render: (l) =>
                l.is_chairman ? (
                    <Tag tone="info">
                        <Crown
                            size={12}
                            strokeWidth={1.75}
                            style={{ marginRight: 4 }}
                        />
                        Председатель
                    </Tag>
                ) : null,
        },
        {
            key: 'sort',
            header: 'Порядок',
            width: 90,
            render: (l) => (
                <span
                    style={{
                        fontSize: 12.5,
                        color: 'var(--color-neutral-700)',
                    }}
                >
                    {l.sort}
                </span>
            ),
        },
        {
            key: 'actions',
            header: '',
            width: 44,
            align: 'right',
            render: (l) =>
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
                                        LeaderController.edit.url(l.id),
                                    ),
                            },
                            ...(can('leadership.delete')
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
                                          onSelect: () => setDeleteTarget(l),
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
            <Head title="Руководство" />
            <PageHeader
                title="Руководство"
                subtitle="Председатель и заместители председателя Комитета — состав страницы «Руководство» на сайте"
                actions={
                    can('leadership.create') && (
                        <LinkButton
                            href={LeaderController.create.url()}
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
                rows={leaders}
                rowKey={(l) => l.id}
                emptyTitle="Руководство ещё не заполнено"
                emptyHint="Добавьте председателя и заместителей."
                emptyAction={
                    can('leadership.create') ? (
                        <LinkButton
                            href={LeaderController.create.url()}
                            variant="secondary"
                        >
                            Добавить запись
                        </LinkButton>
                    ) : undefined
                }
            />

            <ConfirmDialog
                open={!!deleteTarget}
                onClose={() => setDeleteTarget(null)}
                loading={processing}
                title="Удалить запись?"
                body={
                    deleteTarget
                        ? `Запись «${deleteTarget.name}» будет удалена со страницы «Руководство».`
                        : ''
                }
                confirmLabel="Удалить"
                onConfirm={() => {
                    if (!deleteTarget) {
                        return;
                    }

                    setProcessing(true);
                    router.delete(
                        LeaderController.destroy.url(deleteTarget.id),
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
