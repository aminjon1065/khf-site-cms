import { Head, Link, router } from '@inertiajs/react';
import {
    Copy,
    Eye,
    MoreVertical,
    Pin,
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

interface NewsRow {
    id: number;
    title: string;
    slug: string | null;
    status: ContentStatus;
    category: string | null;
    languages: Record<string, number>;
    is_pinned: boolean;
    show_on_home: boolean;
    views_count: number;
    author: string | null;
    cover: string | null;
    published_at: string | null;
    updated_at: string | null;
}

interface Option {
    value: string | number;
    label: string;
}

interface Props {
    news: NewsRow[];
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
        category: string;
        sort: string;
        dir: string;
    };
    savedViews: { key: string; label: string; count: number }[];
    options: {
        statuses: Option[];
        categories: Option[];
    };
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

export default function NewsIndex({
    news,
    meta,
    filters,
    savedViews,
    options,
}: Props) {
    const can = useCan();
    const [deleteTarget, setDeleteTarget] = useState<NewsRow | null>(null);
    const [unpublishTarget, setUnpublishTarget] = useState<NewsRow | null>(
        null,
    );
    const [processing, setProcessing] = useState(false);

    const reload = (patch: Partial<Props['filters']>) => {
        router.get(
            '/news',
            { ...filters, ...patch },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const sort: SortState | null = filters.sort
        ? { key: filters.sort, dir: filters.dir === 'asc' ? 'asc' : 'desc' }
        : null;

    const columns: Column<NewsRow>[] = [
        {
            key: 'title',
            header: 'Заголовок',
            render: (r) => (
                <div style={{ minWidth: 0 }}>
                    <Link
                        href={`/news/${r.id}/edit`}
                        style={{
                            fontWeight: 600,
                            color: 'var(--color-text)',
                            textDecoration: 'none',
                            display: 'inline-flex',
                            alignItems: 'center',
                            gap: 6,
                        }}
                    >
                        {r.is_pinned && (
                            <Pin
                                size={13}
                                strokeWidth={1.75}
                                style={{ color: 'var(--color-accent-600)' }}
                            />
                        )}
                        {r.title}
                    </Link>
                    <div
                        style={{
                            fontSize: 11.5,
                            color: 'var(--color-neutral-500)',
                        }}
                    >
                        {r.slug ?? '—'}
                    </div>
                </div>
            ),
        },
        {
            key: 'category',
            header: 'Категория',
            width: 160,
            render: (r) =>
                r.category ? (
                    <Tag tone="outline">{r.category}</Tag>
                ) : (
                    <span style={{ color: 'var(--color-neutral-400)' }}>—</span>
                ),
        },
        {
            key: 'status',
            header: 'Статус',
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
            key: 'views',
            header: 'Просмотры',
            width: 100,
            sortable: true,
            align: 'right',
            render: (r) => (
                <span style={{ fontSize: 12.5 }} className="ui-mono">
                    {r.views_count.toLocaleString('ru-RU')}
                </span>
            ),
        },
        {
            key: 'author',
            header: 'Автор',
            width: 130,
            render: (r) => (
                <span style={{ fontSize: 12.5 }}>{r.author ?? '—'}</span>
            ),
        },
        {
            key: 'published',
            header: 'Публикация',
            width: 150,
            sortable: true,
            render: (r) => (
                <span style={{ fontSize: 12.5 }} className="ui-mono">
                    {fmt(r.published_at)}
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
                            icon: (
                                <SquareArrowOutUpRight
                                    size={15}
                                    strokeWidth={1.5}
                                />
                            ),
                            onSelect: () => router.visit(`/news/${r.id}/edit`),
                        },
                        {
                            label: 'Предпросмотр',
                            icon: <Eye size={15} strokeWidth={1.5} />,
                            onSelect: () =>
                                window.open('https://khf.tj/news', '_blank'),
                        },
                        ...(can('news.create')
                            ? [
                                  {
                                      label: 'Дублировать',
                                      icon: (
                                          <Copy size={15} strokeWidth={1.5} />
                                      ),
                                      onSelect: () =>
                                          router.post(
                                              `/news/${r.id}/duplicate`,
                                          ),
                                  },
                              ]
                            : []),
                        ...(can('news.publish') &&
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
                        ...(can('news.delete')
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
            <Head title="Новости" />
            <PageHeader
                title="Новости и заявления"
                subtitle="Официальные новости и пресс-релизы Комитета · публикуются на сайте после согласования"
                actions={
                    can('news.create') && (
                        <LinkButton
                            href="/news/create"
                            variant="primary"
                            icon={<Plus size={16} strokeWidth={2} />}
                        >
                            Создать новость
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
                    placeholder="Поиск по заголовку…"
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
                <Select
                    placeholder="Категория: все"
                    value={filters.category}
                    options={options.categories.map((c) => ({
                        value: String(c.value),
                        label: c.label,
                    }))}
                    onChange={(e) => reload({ category: e.target.value })}
                    style={{ width: 'auto' }}
                />
                <span
                    style={{
                        marginLeft: 'auto',
                        fontSize: 12.5,
                        color: 'var(--color-neutral-600)',
                    }}
                >
                    {news.length} из {meta.total} записей
                </span>
            </FilterBar>

            <DataTable
                columns={columns}
                rows={news}
                rowKey={(r) => r.id}
                sort={sort}
                onSortChange={(s) => reload({ sort: s.key, dir: s.dir })}
                emptyTitle="Новостей не найдено"
                emptyHint="Измените фильтры или создайте новую новость."
                emptyAction={
                    can('news.create') ? (
                        <LinkButton href="/news/create" variant="secondary">
                            Создать новость
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
                title="Удалить новость?"
                body={
                    deleteTarget
                        ? `Новость «${deleteTarget.title}» будет перемещена в корзину. Действие фиксируется в журнале.`
                        : ''
                }
                confirmLabel="Удалить"
                onConfirm={() => {
                    if (!deleteTarget) {
                        return;
                    }

                    setProcessing(true);
                    router.delete(`/news/${deleteTarget.id}`, {
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
                title="Снять новость с публикации?"
                body={
                    unpublishTarget
                        ? `Новость «${unpublishTarget.title}» будет убрана с сайта и отправлена в архив.`
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
                        `/news/${unpublishTarget.id}/unpublish`,
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
