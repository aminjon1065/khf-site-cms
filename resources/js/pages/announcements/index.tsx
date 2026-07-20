import { Head, Link, router } from '@inertiajs/react';
import {
    Copy,
    Eye,
    MoreVertical,
    Plus,
    SquareArrowOutUpRight,
    Trash2,
} from 'lucide-react';
import { useState } from 'react';
import { useCan } from '@/lib/auth';
import type { ContentStatus } from '@/lib/domain';
import { LanguageBadges, StatusBadge, Tag } from '@/ui/Badge';
import { IconButton, LinkButton } from '@/ui/Button';
import { DataTable, Pagination } from '@/ui/DataTable';
import type { Column, SortState } from '@/ui/DataTable';
import { Select } from '@/ui/Field';
import { FilterBar, SavedViews, SearchInput } from '@/ui/Filters';
import { ConfirmDialog, Dropdown } from '@/ui/Overlay';
import { PageHeader } from '@/ui/PageHeader';

interface AnnouncementRow {
    id: number;
    title: string;
    kind: string;
    kind_label: string;
    org: string | null;
    status: ContentStatus;
    is_open: boolean;
    languages: Record<string, number>;
    author: string | null;
    deadline: string | null;
    published_at: string | null;
}

interface Option {
    value: string;
    label: string;
}

interface Props {
    announcements: AnnouncementRow[];
    meta: {
        from: number | null;
        to: number | null;
        total: number;
        per_page: number;
        prev: string | null;
        next: string | null;
    };
    filters: {
        view: string;
        search: string;
        status: string;
        kind: string;
        sort: string;
        dir: string;
    };
    savedViews: { key: string; label: string; count: number }[];
    options: {
        statuses: Option[];
        kinds: Option[];
    };
}

function fmt(date: string | null): string {
    return date
        ? new Date(date).toLocaleDateString('ru-RU', {
              day: '2-digit',
              month: '2-digit',
              year: 'numeric',
          })
        : '—';
}

export default function AnnouncementsIndex({
    announcements,
    meta,
    filters,
    savedViews,
    options,
}: Props) {
    const can = useCan();
    const [deleteTarget, setDeleteTarget] = useState<AnnouncementRow | null>(
        null,
    );
    const [unpublishTarget, setUnpublishTarget] =
        useState<AnnouncementRow | null>(null);
    const [processing, setProcessing] = useState(false);

    const reload = (patch: Partial<Props['filters']>) => {
        router.get(
            '/announcements',
            { ...filters, ...patch },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const sort: SortState | null = filters.sort
        ? { key: filters.sort, dir: filters.dir === 'asc' ? 'asc' : 'desc' }
        : null;

    const columns: Column<AnnouncementRow>[] = [
        {
            key: 'kind',
            header: 'Тип',
            width: 120,
            sortable: true,
            render: (r) => (
                <Tag tone={r.kind === 'vacancy' ? 'accent' : 'outline'}>
                    {r.kind_label}
                </Tag>
            ),
        },
        {
            key: 'title',
            header: 'Заголовок',
            render: (r) => (
                <div style={{ minWidth: 0 }}>
                    <Link
                        href={`/announcements/${r.id}/edit`}
                        style={{
                            fontWeight: 600,
                            color: 'var(--color-text)',
                            textDecoration: 'none',
                        }}
                    >
                        {r.title}
                    </Link>
                    {r.org && (
                        <div
                            style={{
                                fontSize: 11.5,
                                color: 'var(--color-neutral-500)',
                            }}
                        >
                            {r.org}
                        </div>
                    )}
                </div>
            ),
        },
        {
            key: 'deadline',
            header: 'Срок',
            width: 130,
            sortable: true,
            render: (r) => (
                <span
                    style={{
                        fontSize: 12.5,
                        color: r.is_open
                            ? 'var(--warn)'
                            : 'var(--color-neutral-500)',
                    }}
                    className="ui-mono"
                >
                    {fmt(r.deadline)}
                </span>
            ),
        },
        {
            key: 'open',
            header: 'Приём',
            width: 110,
            render: (r) => (
                <Tag tone={r.is_open ? 'ok' : 'neutral'}>
                    {r.is_open ? 'Открыт' : 'Завершён'}
                </Tag>
            ),
        },
        {
            key: 'status',
            header: 'Публикация',
            width: 150,
            sortable: true,
            render: (r) => <StatusBadge status={r.status} />,
        },
        {
            key: 'languages',
            header: 'Языки',
            width: 170,
            render: (r) => <LanguageBadges completeness={r.languages} />,
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
                            icon: (
                                <SquareArrowOutUpRight
                                    size={15}
                                    strokeWidth={1.5}
                                />
                            ),
                            onSelect: () =>
                                router.visit(`/announcements/${r.id}/edit`),
                        },
                        {
                            label: 'Предпросмотр',
                            icon: <Eye size={15} strokeWidth={1.5} />,
                            onSelect: () =>
                                window.open(
                                    'https://khf.tj/announcements',
                                    '_blank',
                                ),
                        },
                        ...(can('announcements.create')
                            ? [
                                  {
                                      label: 'Дублировать',
                                      icon: <Copy size={15} strokeWidth={1.5} />,
                                      onSelect: () =>
                                          router.post(
                                              `/announcements/${r.id}/duplicate`,
                                          ),
                                  },
                              ]
                            : []),
                        ...(can('announcements.publish') &&
                        (r.status === 'published' || r.status === 'updated')
                            ? [
                                  { separator: true },
                                  {
                                      label: 'Снять с публикации…',
                                      danger: true,
                                      onSelect: () => setUnpublishTarget(r),
                                  },
                              ]
                            : []),
                        ...(can('announcements.delete')
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
            <Head title="Объявления" />
            <PageHeader
                title="Объявления"
                subtitle="Вакансии государственной службы и тендеры Комитета"
                actions={
                    can('announcements.create') && (
                        <LinkButton
                            href="/announcements/create"
                            variant="primary"
                            icon={<Plus size={16} strokeWidth={2} />}
                        >
                            Создать объявление
                        </LinkButton>
                    )
                }
            />

            <SavedViews
                views={savedViews}
                active={filters.view}
                onChange={(view) => reload({ view })}
            />

            <FilterBar>
                <SearchInput
                    placeholder="Заголовок или подразделение…"
                    defaultValue={filters.search}
                    onChange={(e) => reload({ search: e.target.value })}
                />
                <Select
                    placeholder="Тип: все"
                    value={filters.kind}
                    options={options.kinds}
                    onChange={(e) => reload({ kind: e.target.value })}
                    style={{ width: 'auto' }}
                />
                <Select
                    placeholder="Публикация: все"
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
                    {announcements.length} из {meta.total} записей
                </span>
            </FilterBar>

            <DataTable
                columns={columns}
                rows={announcements}
                rowKey={(r) => r.id}
                sort={sort}
                onSortChange={(s) => reload({ sort: s.key, dir: s.dir })}
                emptyTitle="Объявлений не найдено"
                emptyHint="Измените фильтры или создайте новое объявление."
                emptyAction={
                    can('announcements.create') ? (
                        <LinkButton
                            href="/announcements/create"
                            variant="secondary"
                        >
                            Создать объявление
                        </LinkButton>
                    ) : undefined
                }
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
                title="Удалить объявление?"
                body={
                    deleteTarget
                        ? `Объявление «${deleteTarget.title}» будет перемещено в корзину.`
                        : ''
                }
                confirmLabel="Удалить"
                onConfirm={() => {
                    if (!deleteTarget) {
                        return;
                    }

                    setProcessing(true);
                    router.delete(`/announcements/${deleteTarget.id}`, {
                        preserveScroll: true,
                        onFinish: () => {
                            setProcessing(false);
                            setDeleteTarget(null);
                        },
                    });
                }}
            />

            <ConfirmDialog
                open={!!unpublishTarget}
                onClose={() => setUnpublishTarget(null)}
                loading={processing}
                title="Снять объявление с публикации?"
                body={
                    unpublishTarget
                        ? `Объявление «${unpublishTarget.title}» будет убрано с сайта и отправлено в архив.`
                        : ''
                }
                confirmLabel="Снять с публикации"
                requireComment
                onConfirm={(comment) => {
                    if (!unpublishTarget) {
                        return;
                    }

                    setProcessing(true);
                    router.post(
                        `/announcements/${unpublishTarget.id}/unpublish`,
                        { comment },
                        {
                            preserveScroll: true,
                            onFinish: () => {
                                setProcessing(false);
                                setUnpublishTarget(null);
                            },
                        },
                    );
                }}
            />
        </>
    );
}
