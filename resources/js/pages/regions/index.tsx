import { Head, Link, router } from '@inertiajs/react';
import { MoreVertical, Pencil, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { useCan } from '@/lib/auth';
import { Tag } from '@/ui/Badge';
import { IconButton, LinkButton } from '@/ui/Button';
import { DataTable } from '@/ui/DataTable';
import type { Column } from '@/ui/DataTable';
import { ConfirmDialog, Dropdown } from '@/ui/Overlay';
import { PageHeader } from '@/ui/PageHeader';

type Level = 'none' | 'info' | 'warning' | 'danger' | 'critical';

interface RegionRow {
    id: number;
    name: string;
    code: string;
    type: string;
    regional_center: string | null;
    phone: string | null;
    districts_count: number;
    curated_count: number;
    level: Level;
    active_count: number;
    status_text: string;
}

interface Props {
    regions: RegionRow[];
}

const LEVEL_TONE: Record<Level, 'ok' | 'info' | 'warn' | 'danger'> = {
    none: 'ok',
    info: 'info',
    warning: 'warn',
    danger: 'danger',
    critical: 'danger',
};

export default function RegionsIndex({ regions }: Props) {
    const can = useCan();
    const editable = can('regions.edit');
    const [deleteTarget, setDeleteTarget] = useState<RegionRow | null>(null);
    const [processing, setProcessing] = useState(false);

    const columns: Column<RegionRow>[] = [
        {
            key: 'name',
            header: 'Регион',
            render: (r) => (
                <div style={{ minWidth: 0 }}>
                    {editable ? (
                        <Link
                            href={`/regions/${r.id}/edit`}
                            style={{
                                fontWeight: 600,
                                color: 'var(--color-text)',
                                textDecoration: 'none',
                            }}
                        >
                            {r.name}
                        </Link>
                    ) : (
                        <span style={{ fontWeight: 600 }}>{r.name}</span>
                    )}
                    <div
                        style={{
                            fontSize: 11.5,
                            color: 'var(--color-neutral-500)',
                        }}
                    >
                        <span className="ui-mono">{r.code}</span> · {r.type}
                    </div>
                </div>
            ),
        },
        {
            key: 'center',
            header: 'Центр',
            width: 160,
            render: (r) => (
                <span
                    style={{
                        fontSize: 12.5,
                        color: 'var(--color-neutral-700)',
                    }}
                >
                    {r.regional_center ?? '—'}
                </span>
            ),
        },
        {
            key: 'districts',
            header: 'Районы',
            width: 130,
            render: (r) => (
                <span
                    style={{
                        fontSize: 12.5,
                        color: 'var(--color-neutral-700)',
                    }}
                    title="В справочнике / всего официально"
                >
                    {r.curated_count} / {r.districts_count}
                </span>
            ),
        },
        {
            key: 'status',
            header: 'Обстановка',
            width: 190,
            render: (r) => (
                <Tag tone={LEVEL_TONE[r.level]}>
                    {r.status_text}
                    {r.active_count > 0 ? ` · ${r.active_count}` : ''}
                </Tag>
            ),
        },
        {
            key: 'actions',
            header: '',
            width: 44,
            align: 'right',
            render: (r) =>
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
                                    router.visit(`/regions/${r.id}/edit`),
                            },
                            ...(can('regions.delete')
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
                                          onSelect: () => setDeleteTarget(r),
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
            <Head title="Регионы и районы" />
            <PageHeader
                title="Регионы и районы"
                subtitle="Региональные управления, контакты и справочник районов · обстановка вычисляется по активным предупреждениям"
                actions={
                    can('regions.create') && (
                        <LinkButton
                            href="/regions/create"
                            variant="primary"
                            icon={<Plus size={16} strokeWidth={2} />}
                        >
                            Добавить регион
                        </LinkButton>
                    )
                }
            />

            <DataTable
                columns={columns}
                rows={regions}
                rowKey={(r) => r.id}
                emptyTitle="Регионов пока нет"
                emptyHint="Добавьте первый регион республики."
                emptyAction={
                    can('regions.create') ? (
                        <LinkButton href="/regions/create" variant="secondary">
                            Добавить регион
                        </LinkButton>
                    ) : undefined
                }
            />

            <ConfirmDialog
                open={!!deleteTarget}
                onClose={() => setDeleteTarget(null)}
                loading={processing}
                title="Удалить регион?"
                body={
                    deleteTarget
                        ? `Регион «${deleteTarget.name}» и его районы будут удалены. Регион, используемый в предупреждениях, удалить нельзя.`
                        : ''
                }
                confirmLabel="Удалить"
                onConfirm={() => {
                    if (!deleteTarget) {
                        return;
                    }

                    setProcessing(true);
                    router.delete(`/regions/${deleteTarget.id}`, {
                        preserveScroll: true,
                        onFinish: () => {
                            setProcessing(false);
                            setDeleteTarget(null);
                        },
                    });
                }}
            />
        </>
    );
}
