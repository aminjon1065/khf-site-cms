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

interface ProjectRow {
    id: number;
    title: string;
    slug: string | null;
    status: ContentStatus;
    lifecycle_status: string;
    lifecycle_label: string;
    lifecycle_tone: string;
    years: string | null;
    partner: string | null;
    budget: string | null;
    languages: Record<string, number>;
    author: string | null;
    published_at: string | null;
}

interface Option {
    value: string;
    label: string;
}

interface Props {
    projects: ProjectRow[];
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
        lifecycle: string;
        sort: string;
        dir: string;
    };
    savedViews: { key: string; label: string; count: number }[];
    options: {
        statuses: Option[];
        lifecycles: Option[];
    };
}

const TONE_TO_TAG: Record<string, 'ok' | 'info' | 'neutral'> = {
    success: 'ok',
    info: 'info',
    neutral: 'neutral',
};

export default function ProjectsIndex({
    projects,
    meta,
    filters,
    savedViews,
    options,
}: Props) {
    const can = useCan();
    const [deleteTarget, setDeleteTarget] = useState<ProjectRow | null>(null);
    const [unpublishTarget, setUnpublishTarget] = useState<ProjectRow | null>(
        null,
    );
    const [processing, setProcessing] = useState(false);

    const reload = (patch: Partial<Props['filters']>) => {
        router.get(
            '/projects',
            { ...filters, ...patch },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const sort: SortState | null = filters.sort
        ? { key: filters.sort, dir: filters.dir === 'asc' ? 'asc' : 'desc' }
        : null;

    const columns: Column<ProjectRow>[] = [
        {
            key: 'title',
            header: 'Проект',
            render: (r) => (
                <div style={{ minWidth: 0 }}>
                    <Link
                        href={`/projects/${r.id}/edit`}
                        style={{
                            fontWeight: 600,
                            color: 'var(--color-text)',
                            textDecoration: 'none',
                        }}
                    >
                        {r.title}
                    </Link>
                    <div
                        style={{
                            fontSize: 11.5,
                            color: 'var(--color-neutral-500)',
                        }}
                    >
                        {[r.slug, r.years].filter(Boolean).join(' · ') || '—'}
                    </div>
                </div>
            ),
        },
        {
            key: 'lifecycle',
            header: 'Статус проекта',
            width: 150,
            render: (r) => (
                <Tag tone={TONE_TO_TAG[r.lifecycle_tone] ?? 'neutral'}>
                    {r.lifecycle_label}
                </Tag>
            ),
        },
        {
            key: 'partner',
            header: 'Партнёр',
            width: 200,
            render: (r) => (
                <span
                    style={{
                        fontSize: 12.5,
                        color: 'var(--color-neutral-700)',
                    }}
                >
                    {r.partner ?? '—'}
                </span>
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
                                router.visit(`/projects/${r.id}/edit`),
                        },
                        {
                            label: 'Предпросмотр',
                            icon: <Eye size={15} strokeWidth={1.5} />,
                            onSelect: () =>
                                window.open('https://khf.tj/projects', '_blank'),
                        },
                        ...(can('projects.create')
                            ? [
                                  {
                                      label: 'Дублировать',
                                      icon: <Copy size={15} strokeWidth={1.5} />,
                                      onSelect: () =>
                                          router.post(
                                              `/projects/${r.id}/duplicate`,
                                          ),
                                  },
                              ]
                            : []),
                        ...(can('projects.publish') &&
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
                        ...(can('projects.delete')
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
            <Head title="Проекты и программы" />
            <PageHeader
                title="Проекты и программы"
                subtitle="Государственные программы и проекты технической помощи Комитета"
                actions={
                    can('projects.create') && (
                        <LinkButton
                            href="/projects/create"
                            variant="primary"
                            icon={<Plus size={16} strokeWidth={2} />}
                        >
                            Создать проект
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
                    placeholder="Поиск по названию…"
                    defaultValue={filters.search}
                    onChange={(e) => reload({ search: e.target.value })}
                />
                <Select
                    placeholder="Статус проекта: все"
                    value={filters.lifecycle}
                    options={options.lifecycles}
                    onChange={(e) => reload({ lifecycle: e.target.value })}
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
                    {projects.length} из {meta.total} записей
                </span>
            </FilterBar>

            <DataTable
                columns={columns}
                rows={projects}
                rowKey={(r) => r.id}
                sort={sort}
                onSortChange={(s) => reload({ sort: s.key, dir: s.dir })}
                emptyTitle="Проектов не найдено"
                emptyHint="Измените фильтры или создайте новый проект."
                emptyAction={
                    can('projects.create') ? (
                        <LinkButton href="/projects/create" variant="secondary">
                            Создать проект
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
                title="Удалить проект?"
                body={
                    deleteTarget
                        ? `Проект «${deleteTarget.title}» будет перемещён в корзину.`
                        : ''
                }
                confirmLabel="Удалить"
                onConfirm={() => {
                    if (!deleteTarget) {
                        return;
                    }

                    setProcessing(true);
                    router.delete(`/projects/${deleteTarget.id}`, {
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
                title="Снять проект с публикации?"
                body={
                    unpublishTarget
                        ? `Проект «${unpublishTarget.title}» будет убран с сайта и отправлен в архив.`
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
                        `/projects/${unpublishTarget.id}/unpublish`,
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
