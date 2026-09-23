import { Head, Link, router } from '@inertiajs/react';
import {
    Copy,
    MoreVertical,
    Plus,
    SquareArrowOutUpRight,
    Trash2,
} from 'lucide-react';
import { useState } from 'react';
import PageController from '@/actions/App/Http/Controllers/Cms/PageController';
import { useBulkActions } from '@/cms/BulkActions';
import { TrashLink } from '@/cms/TrashLink';
import { useCan } from '@/lib/auth';
import type { ContentStatus } from '@/lib/domain';
import { LanguageBadges, StatusBadge } from '@/ui/Badge';
import { IconButton, LinkButton } from '@/ui/Button';
import { DataTable, Pagination } from '@/ui/DataTable';
import type { Column, SortState } from '@/ui/DataTable';
import { Select } from '@/ui/Field';
import { FilterBar, SavedViews, SearchInput } from '@/ui/Filters';
import { ConfirmDialog, Dropdown } from '@/ui/Overlay';
import { PageHeader } from '@/ui/PageHeader';

interface PageRow {
    id: number;
    title: string;
    slug: string;
    /** Where the page lives on the site: a section (`/leadership`) or `/pages/{slug}`. */
    public_path: string;
    /** Null while the page isn't visible on the site. */
    public_url: string | null;
    /** The site depends on this page's slug: it can't be renamed or deleted. */
    is_system: boolean;
    status: ContentStatus;
    parent: string | null;
    languages: Record<string, number>;
    author: string | null;
    published_at: string | null;
    updated_at: string | null;
}

interface Option {
    value: string;
    label: string;
}

interface Props {
    trash_count: number;
    pages: PageRow[];
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
        sort: string;
        dir: string;
    };
    savedViews: { key: string; label: string; count: number }[];
    options: { statuses: Option[] };
}

export default function PagesIndex({
    trash_count,
    pages,
    meta,
    filters,
    savedViews,
    options,
}: Props) {
    const can = useCan();
    const bulk = useBulkActions({
        type: 'pages',
        rows: pages,
        rowTitle: (r) => r.title,
        allowed: {
            submit: can('pages.edit'),
            publish: can('pages.publish'),
            trash: can('pages.delete'),
        },
    });
    const [deleteTarget, setDeleteTarget] = useState<PageRow | null>(null);
    const [unpublishTarget, setUnpublishTarget] = useState<PageRow | null>(
        null,
    );
    const [processing, setProcessing] = useState(false);

    const reload = (patch: Partial<Props['filters']>) => {
        router.get(
            PageController.index.url(),
            { ...filters, ...patch },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const sort: SortState | null = filters.sort
        ? { key: filters.sort, dir: filters.dir === 'asc' ? 'asc' : 'desc' }
        : null;

    const columns: Column<PageRow>[] = [
        {
            key: 'title',
            header: 'Страница',
            sortable: true,
            render: (r) => (
                <div style={{ minWidth: 0 }}>
                    <Link
                        href={PageController.edit.url(r.id)}
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
                        <span className="ui-mono">{r.public_path}</span>
                        {r.parent ? ` · ${r.parent}` : ''}
                        {r.is_system && (
                            <>
                                {' · '}
                                <span title="Адрес закреплён за сайтом: страницу нельзя переименовать или удалить">
                                    {r.public_path.startsWith('/pages/')
                                        ? 'ссылка в подвале сайта'
                                        : 'раздел сайта'}
                                </span>
                            </>
                        )}
                    </div>
                    <div className="wp-row-actions">
                        <Link
                            href={PageController.edit.url(r.id)}
                            onClick={(e) => e.stopPropagation()}
                        >
                            Изменить
                        </Link>
                        {r.public_url && (
                            <>
                                <span className="wp-row-action-sep">|</span>
                                <a
                                    href={r.public_url}
                                    target="_blank"
                                    rel="noreferrer"
                                    onClick={(e) => e.stopPropagation()}
                                >
                                    На сайте
                                </a>
                            </>
                        )}
                    </div>
                </div>
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
                                router.visit(PageController.edit.url(r.id)),
                        },
                        ...(can('pages.create')
                            ? [
                                  {
                                      label: 'Дублировать',
                                      icon: (
                                          <Copy size={15} strokeWidth={1.5} />
                                      ),
                                      onSelect: () =>
                                          router.post(
                                              PageController.duplicate.url(
                                                  r.id,
                                              ),
                                          ),
                                  },
                              ]
                            : []),
                        ...(can('pages.publish') &&
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
                        ...(can('pages.delete') && !r.is_system
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
            <Head title="Страницы сайта" />
            <PageHeader
                title="Страницы сайта"
                subtitle="Информационные страницы портала (о Комитете, правовая информация)"
                actions={
                    can('pages.create') && (
                        <LinkButton
                            href={PageController.create.url()}
                            variant="primary"
                            icon={<Plus size={16} strokeWidth={2} />}
                        >
                            Создать страницу
                        </LinkButton>
                    )
                }
            />

            <SavedViews
                views={savedViews}
                active={filters.view}
                onChange={(view) => reload({ view })}
                trailing={
                    trash_count > 0 && (
                        <TrashLink type="pages" count={trash_count} />
                    )
                }
            />

            <FilterBar>
                <SearchInput
                    placeholder="Заголовок или адрес…"
                    defaultValue={filters.search}
                    onChange={(e) => reload({ search: e.target.value })}
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
                    {pages.length} из {meta.total} записей
                </span>
            </FilterBar>

            <DataTable
                columns={columns}
                rows={pages}
                rowKey={(r) => r.id}
                {...bulk.table}
                sort={sort}
                onRowClick={(r) => router.visit(PageController.edit.url(r.id))}
                onSortChange={(s) => reload({ sort: s.key, dir: s.dir })}
                emptyTitle="Страниц не найдено"
                emptyHint="Измените фильтры или создайте новую страницу."
                emptyAction={
                    can('pages.create') ? (
                        <LinkButton
                            href={PageController.create.url()}
                            variant="secondary"
                        >
                            Создать страницу
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
                title="Удалить страницу?"
                body={
                    deleteTarget
                        ? `Страница «${deleteTarget.title}» будет перемещена в корзину.`
                        : ''
                }
                confirmLabel="Удалить"
                onConfirm={() => {
                    if (!deleteTarget) {
                        return;
                    }

                    setProcessing(true);
                    router.delete(PageController.destroy.url(deleteTarget.id), {
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
                title="Снять страницу с публикации?"
                body={
                    unpublishTarget
                        ? `Страница «${unpublishTarget.title}» будет убрана с сайта и вернётся в черновики.`
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
                        PageController.unpublish.url(unpublishTarget.id),
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

            {bulk.dialogs}
        </>
    );
}
