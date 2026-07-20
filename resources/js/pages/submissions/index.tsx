import { Head, Link, router } from '@inertiajs/react';
import { Eye, MoreVertical, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { useCan } from '@/lib/auth';
import { Tag } from '@/ui/Badge';
import { IconButton } from '@/ui/Button';
import { DataTable, Pagination } from '@/ui/DataTable';
import type { Column } from '@/ui/DataTable';
import { Select } from '@/ui/Field';
import { FilterBar, SavedViews, SearchInput } from '@/ui/Filters';
import { ConfirmDialog, Dropdown } from '@/ui/Overlay';
import { PageHeader } from '@/ui/PageHeader';

type Tone = 'accent' | 'neutral' | 'outline' | 'ok' | 'warn' | 'danger' | 'info';

interface SubmissionRow {
    id: number;
    tracking_number: string | null;
    name: string;
    email: string;
    topic: string | null;
    status: string;
    status_label: string;
    status_tone: string;
    assignee: string | null;
    created_at: string | null;
}

interface Option {
    value: string;
    label: string;
}

interface Props {
    submissions: SubmissionRow[];
    meta: {
        from: number | null;
        to: number | null;
        total: number;
        per_page: number;
        prev: string | null;
        next: string | null;
    };
    filters: { view: string; search: string; status: string };
    savedViews: { key: string; label: string; count: number }[];
    options: { statuses: Option[] };
}

function fmt(date: string | null): string {
    return date
        ? new Date(date).toLocaleString('ru-RU', {
              day: '2-digit',
              month: '2-digit',
              year: 'numeric',
              hour: '2-digit',
              minute: '2-digit',
          })
        : '—';
}

export default function SubmissionsIndex({
    submissions,
    meta,
    filters,
    savedViews,
    options,
}: Props) {
    const can = useCan();
    const [deleteTarget, setDeleteTarget] = useState<SubmissionRow | null>(null);
    const [processing, setProcessing] = useState(false);

    const reload = (patch: Partial<Props['filters']>) => {
        router.get(
            '/submissions',
            { ...filters, ...patch },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const columns: Column<SubmissionRow>[] = [
        {
            key: 'tracking',
            header: 'Номер',
            width: 150,
            render: (r) => (
                <Link
                    href={`/submissions/${r.id}`}
                    className="ui-mono"
                    style={{
                        fontSize: 12.5,
                        fontWeight: 600,
                        color: 'var(--color-accent-700)',
                        textDecoration: 'none',
                    }}
                >
                    {r.tracking_number ?? `#${r.id}`}
                </Link>
            ),
        },
        {
            key: 'name',
            header: 'Заявитель',
            render: (r) => (
                <div style={{ minWidth: 0 }}>
                    <div style={{ fontWeight: 600 }}>{r.name}</div>
                    <div
                        style={{
                            fontSize: 11.5,
                            color: 'var(--color-neutral-500)',
                        }}
                    >
                        {r.email}
                    </div>
                </div>
            ),
        },
        {
            key: 'topic',
            header: 'Тема',
            width: 200,
            render: (r) => (
                <span style={{ fontSize: 12.5 }}>{r.topic ?? '—'}</span>
            ),
        },
        {
            key: 'status',
            header: 'Статус',
            width: 150,
            render: (r) => (
                <Tag tone={(r.status_tone as Tone) ?? 'neutral'}>
                    {r.status_label}
                </Tag>
            ),
        },
        {
            key: 'assignee',
            header: 'Ответственный',
            width: 150,
            render: (r) => (
                <span style={{ fontSize: 12.5 }}>{r.assignee ?? '—'}</span>
            ),
        },
        {
            key: 'created',
            header: 'Получено',
            width: 150,
            render: (r) => (
                <span style={{ fontSize: 12.5 }} className="ui-mono">
                    {fmt(r.created_at)}
                </span>
            ),
        },
        {
            key: 'actions',
            header: '',
            width: 44,
            align: 'right',
            render: (r) => (
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
                            label: 'Открыть',
                            icon: <Eye size={15} strokeWidth={1.5} />,
                            onSelect: () =>
                                router.visit(`/submissions/${r.id}`),
                        },
                        ...(can('submissions.delete')
                            ? [
                                  { separator: true },
                                  {
                                      label: 'Удалить…',
                                      icon: (
                                          <Trash2 size={15} strokeWidth={1.5} />
                                      ),
                                      danger: true,
                                      onSelect: () => setDeleteTarget(r),
                                  },
                              ]
                            : []),
                    ]}
                />
            ),
        },
    ];

    return (
        <>
            <Head title="Обращения граждан" />
            <PageHeader
                title="Обращения граждан"
                subtitle="Электронная приёмная · обращения, поступившие с публичного сайта"
            />

            <SavedViews
                views={savedViews}
                active={filters.view}
                onChange={(view) => reload({ view })}
            />

            <FilterBar>
                <SearchInput
                    placeholder="Имя, email, номер…"
                    defaultValue={filters.search}
                    onChange={(e) => reload({ search: e.target.value })}
                />
                <Select
                    placeholder="Статус: все"
                    value={filters.status}
                    options={options.statuses}
                    onChange={(e) => reload({ status: e.target.value })}
                    style={{ width: 'auto' }}
                />
                <span
                    style={{
                        marginLeft: 'auto',
                        fontSize: 12.5,
                        color: 'var(--color-neutral-600)',
                    }}
                >
                    {submissions.length} из {meta.total} записей
                </span>
            </FilterBar>

            <DataTable
                columns={columns}
                rows={submissions}
                rowKey={(r) => r.id}
                emptyTitle="Обращений не найдено"
                emptyHint="С публичного сайта пока не поступало обращений по этому фильтру."
            />

            <Pagination
                from={meta.from ?? 0}
                to={meta.to ?? 0}
                total={meta.total}
                perPage={meta.per_page}
                onPerPageChange={(n) =>
                    reload({ ...filters, per_page: String(n) } as never)
                }
                onPrev={
                    meta.prev
                        ? () =>
                              router.visit(meta.prev!, {
                                  preserveState: true,
                                  preserveScroll: true,
                              })
                        : undefined
                }
                onNext={
                    meta.next
                        ? () =>
                              router.visit(meta.next!, {
                                  preserveState: true,
                                  preserveScroll: true,
                              })
                        : undefined
                }
            />

            <ConfirmDialog
                open={!!deleteTarget}
                onClose={() => setDeleteTarget(null)}
                loading={processing}
                title="Удалить обращение?"
                body={
                    deleteTarget
                        ? `Обращение ${deleteTarget.tracking_number ?? ''} будет перемещено в корзину.`
                        : ''
                }
                confirmLabel="Удалить"
                onConfirm={() => {
                    if (!deleteTarget) {
                        return;
                    }

                    setProcessing(true);
                    router.delete(`/submissions/${deleteTarget.id}`, {
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
