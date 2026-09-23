import { Head, Link, router } from '@inertiajs/react';
import {
    Copy,
    Eye,
    MoreVertical,
    Pin,
    Plus,
    SquareArrowOutUpRight,
    Trash2,
    Wand2,
    Zap,
} from 'lucide-react';
import { useState } from 'react';
import NewsController from '@/actions/App/Http/Controllers/Cms/NewsController';
import { useBulkActions } from '@/cms/BulkActions';
import { TrashLink } from '@/cms/TrashLink';
import { useRememberedView } from '@/hooks/use-remembered-view';
import { useCan } from '@/lib/auth';
import { LIVE_STATUSES } from '@/lib/domain';
import type { ContentStatus } from '@/lib/domain';
import { patchJson } from '@/lib/http';
import { slugify } from '@/lib/slugify';
import { LanguageBadges, StatusBadge, Tag } from '@/ui/Badge';
import { Button, IconButton, LinkButton } from '@/ui/Button';
import { DataTable, Pagination } from '@/ui/DataTable';
import type { Column, SortState } from '@/ui/DataTable';
import { Checkbox, Field, Input, Select } from '@/ui/Field';
import { FilterBar, SavedViews, SearchInput } from '@/ui/Filters';
import { ConfirmDialog, Dropdown } from '@/ui/Overlay';
import { PageHeader } from '@/ui/PageHeader';
import { useToast } from '@/ui/Toast';

interface NewsRow {
    id: number;
    title: string;
    slug: string | null;
    /** Null while the item isn't visible on the public site. */
    public_url: string | null;
    status: ContentStatus;
    category: string | null;
    category_id?: number | null;
    languages: Record<string, number>;
    is_pinned: boolean;
    show_on_home: boolean;
    views_count: number;
    author: string | null;
    cover: string | null;
    published_at: string | null;
    scheduled_at?: string | null;
    updated_at: string | null;
}

interface Option {
    value: string | number;
    label: string;
}

interface Props {
    trash_count: number;
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
    trash_count,
    news,
    meta,
    filters,
    savedViews,
    options,
}: Props) {
    const can = useCan();
    const bulk = useBulkActions({
        type: 'news',
        rows: news,
        rowTitle: (r) => r.title,
        allowed: {
            submit: can('news.edit'),
            publish: can('news.publish'),
            trash: can('news.delete'),
        },
    });
    const toast = useToast();
    const [newsItems, setNewsItems] = useState<NewsRow[]>(news);
    const [prevNews, setPrevNews] = useState<NewsRow[]>(news);

    if (prevNews !== news) {
        setPrevNews(news);
        setNewsItems(news);
    }

    const [deleteTarget, setDeleteTarget] = useState<NewsRow | null>(null);
    const [unpublishTarget, setUnpublishTarget] = useState<NewsRow | null>(
        null,
    );
    const [processing, setProcessing] = useState(false);

    // Быстрое редактирование (WordPress Quick Edit)
    const [quickEditId, setQuickEditId] = useState<number | null>(null);
    const [quickData, setQuickData] = useState<{
        title: string;
        slug: string;
        category_id: string;
        is_pinned: boolean;
        show_on_home: boolean;
        published_at: string;
        updated_at: string | null;
    }>({
        title: '',
        slug: '',
        category_id: '',
        is_pinned: false,
        show_on_home: true,
        published_at: '',
        updated_at: null,
    });
    const [quickSaving, setQuickSaving] = useState(false);
    const [quickError, setQuickError] = useState<string | null>(null);

    const startQuickEdit = (r: NewsRow) => {
        setQuickEditId(r.id);
        setQuickError(null);
        setQuickData({
            title: r.title === '— без заголовка —' ? '' : r.title,
            slug: r.slug ?? '',
            category_id: r.category_id ? String(r.category_id) : '',
            is_pinned: r.is_pinned,
            show_on_home: r.show_on_home,
            published_at: r.published_at ? r.published_at.slice(0, 16) : '',
            updated_at: r.updated_at,
        });
    };

    const cancelQuickEdit = () => {
        setQuickEditId(null);
        setQuickError(null);
    };

    const handleSaveQuickEdit = async (rowId: number) => {
        setQuickSaving(true);
        setQuickError(null);

        try {
            const res = await patchJson<{
                success: boolean;
                news?: NewsRow;
                pending?: boolean;
                message?: string;
            }>(`/news/${rowId}/quick-update`, {
                title: quickData.title,
                slug: quickData.slug,
                category_id: quickData.category_id
                    ? Number(quickData.category_id)
                    : null,
                is_pinned: quickData.is_pinned,
                show_on_home: quickData.show_on_home,
                // The publication date is a publishing decision: only people
                // allowed to publish can move it.
                ...(can('news.publish')
                    ? { published_at: quickData.published_at || null }
                    : {}),
                _editorial_version: quickData.updated_at,
            });

            const saved = res.news;

            if (saved) {
                setNewsItems((prev) =>
                    prev.map((item) =>
                        item.id === rowId ? { ...item, ...saved } : item,
                    ),
                );
            }

            if (res.pending && res.message) {
                toast(res.message, 'info');
            }

            setQuickEditId(null);
        } catch (err: any) {
            setQuickError(err.message || 'Не удалось сохранить изменения');
        } finally {
            setQuickSaving(false);
        }
    };

    const reload = (patch: Partial<Props['filters']>) => {
        router.get(
            NewsController.index.url(),
            { ...filters, ...patch },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const changeView = useRememberedView('news', filters.view, (view) =>
        reload({ view }),
    );

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
                        href={NewsController.edit.url(r.id)}
                        style={{
                            fontWeight: 600,
                            color: 'var(--color-text)',
                            textDecoration: 'none',
                            display: 'flex',
                            alignItems: 'center',
                            gap: 6,
                            minWidth: 0,
                        }}
                    >
                        {r.is_pinned && (
                            <Pin
                                size={13}
                                strokeWidth={1.75}
                                style={{
                                    color: 'var(--brand-600)',
                                    flex: 'none',
                                }}
                            />
                        )}
                        <span
                            style={{
                                overflow: 'hidden',
                                textOverflow: 'ellipsis',
                                whiteSpace: 'nowrap',
                            }}
                        >
                            {r.title}
                        </span>
                    </Link>
                    <div
                        style={{
                            fontSize: 11.5,
                            color: 'var(--color-neutral-500)',
                            overflow: 'hidden',
                            textOverflow: 'ellipsis',
                            whiteSpace: 'nowrap',
                        }}
                        title={r.slug ?? undefined}
                    >
                        {r.slug ?? '—'}
                    </div>

                    <div className="wp-row-actions">
                        <Link
                            href={NewsController.edit.url(r.id)}
                            onClick={(e) => e.stopPropagation()}
                        >
                            Изменить
                        </Link>
                        {can('news.edit') && (
                            <>
                                <span className="wp-row-action-sep">|</span>
                                <button
                                    type="button"
                                    onClick={(e) => {
                                        e.stopPropagation();
                                        startQuickEdit(r);
                                    }}
                                >
                                    Свойства
                                </button>
                            </>
                        )}
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
            key: 'category',
            header: 'Рубрика',
            width: 160,
            optional: 2,
            render: (r) =>
                r.category ? (
                    <Tag tone="outline">{r.category}</Tag>
                ) : (
                    <span style={{ color: 'var(--color-neutral-500)' }}>—</span>
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
            optional: 1,
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
            optional: 1,
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
                            onSelect: () =>
                                router.visit(NewsController.edit.url(r.id)),
                        },
                        ...(r.public_url
                            ? [
                                  {
                                      label: 'Открыть на сайте',
                                      icon: <Eye size={15} strokeWidth={1.5} />,
                                      onSelect: () =>
                                          window.open(r.public_url!, '_blank'),
                                  },
                              ]
                            : []),
                        ...(can('news.edit')
                            ? [
                                  {
                                      label: 'Свойства (быстро)',
                                      icon: <Zap size={15} strokeWidth={1.5} />,
                                      onSelect: () => startQuickEdit(r),
                                  },
                              ]
                            : []),
                        ...(can('news.create')
                            ? [
                                  {
                                      label: 'Дублировать',
                                      icon: (
                                          <Copy size={15} strokeWidth={1.5} />
                                      ),
                                      onSelect: () =>
                                          router.post(
                                              NewsController.duplicate.url(
                                                  r.id,
                                              ),
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

    const renderSubRow = (r: NewsRow) => {
        if (quickEditId !== r.id) {
            return null;
        }

        const needsApproval =
            LIVE_STATUSES.includes(r.status) && !can('news.publish');

        return (
            <div
                className="wp-quick-edit-panel"
                onClick={(e) => e.stopPropagation()}
            >
                <div
                    style={{
                        display: 'flex',
                        alignItems: 'center',
                        justifyContent: 'space-between',
                        marginBottom: 10,
                    }}
                >
                    <strong
                        style={{ fontSize: 13, color: 'var(--color-text)' }}
                    >
                        Быстрое редактирование: {r.title}
                    </strong>
                </div>

                {needsApproval && (
                    <p
                        style={{
                            fontSize: 12,
                            color: 'var(--color-neutral-600)',
                            margin: '0 0 10px',
                        }}
                    >
                        Новость уже на сайте: изменения уйдут на согласование, а
                        до решения на сайте останется прежняя версия.
                    </p>
                )}

                {quickError && (
                    <div
                        style={{
                            color: 'var(--danger, #dc2626)',
                            fontSize: 12,
                            marginBottom: 10,
                        }}
                    >
                        {quickError}
                    </div>
                )}

                <div className="wp-quick-edit-grid">
                    {/* Колонка 1: Заголовок, Slug, Дата */}
                    <div>
                        <Field
                            label="Заголовок"
                            htmlFor={`quick-title-${r.id}`}
                        >
                            <Input
                                id={`quick-title-${r.id}`}
                                value={quickData.title}
                                onChange={(e) =>
                                    setQuickData((d) => ({
                                        ...d,
                                        title: e.target.value,
                                    }))
                                }
                                placeholder="Заголовок новости"
                            />
                        </Field>

                        <Field
                            label="Адрес ссылки"
                            htmlFor={`quick-slug-${r.id}`}
                            className="mt-2"
                        >
                            <div style={{ display: 'flex', gap: 6 }}>
                                <Input
                                    id={`quick-slug-${r.id}`}
                                    value={quickData.slug}
                                    onChange={(e) =>
                                        setQuickData((d) => ({
                                            ...d,
                                            slug: e.target.value,
                                        }))
                                    }
                                    placeholder="adres-novosti"
                                    style={{ flex: 1 }}
                                />
                                <Button
                                    type="button"
                                    size="sm"
                                    variant="secondary"
                                    icon={<Wand2 size={13} />}
                                    title="Составить адрес из заголовка"
                                    onClick={() => {
                                        if (quickData.title) {
                                            setQuickData((d) => ({
                                                ...d,
                                                slug: slugify(quickData.title),
                                            }));
                                        }
                                    }}
                                >
                                    Из заголовка
                                </Button>
                            </div>
                        </Field>

                        {can('news.publish') && (
                            <Field
                                label="Дата публикации (время Душанбе)"
                                htmlFor={`quick-date-${r.id}`}
                                className="mt-2"
                            >
                                <Input
                                    id={`quick-date-${r.id}`}
                                    type="datetime-local"
                                    value={quickData.published_at}
                                    onChange={(e) =>
                                        setQuickData((d) => ({
                                            ...d,
                                            published_at: e.target.value,
                                        }))
                                    }
                                />
                            </Field>
                        )}
                    </div>

                    {/* Колонка 2: Рубрика. Статус меняется только кнопками
                        публикации — через согласование и проверки. */}
                    <div>
                        <Field label="Рубрика" htmlFor={`quick-cat-${r.id}`}>
                            <Select
                                id={`quick-cat-${r.id}`}
                                value={quickData.category_id}
                                onChange={(e) =>
                                    setQuickData((d) => ({
                                        ...d,
                                        category_id: e.target.value,
                                    }))
                                }
                                placeholder="Без рубрики"
                                options={options.categories.map((c) => ({
                                    value: String(c.value),
                                    label: c.label,
                                }))}
                            />
                        </Field>
                    </div>

                    {/* Колонка 3: Закрепление и Главная страница */}
                    <div>
                        <span
                            style={{
                                fontSize: 12,
                                fontWeight: 600,
                                color: 'var(--color-neutral-700)',
                                display: 'block',
                                marginBottom: 6,
                            }}
                        >
                            Отображение
                        </span>
                        <div
                            style={{
                                display: 'flex',
                                flexDirection: 'column',
                                gap: 8,
                            }}
                        >
                            <Checkbox
                                label="Закрепить вверху ленты"
                                checked={quickData.is_pinned}
                                onChange={(e) =>
                                    setQuickData((d) => ({
                                        ...d,
                                        is_pinned: e.target.checked,
                                    }))
                                }
                            />
                            <Checkbox
                                label="Показывать на главной"
                                checked={quickData.show_on_home}
                                onChange={(e) =>
                                    setQuickData((d) => ({
                                        ...d,
                                        show_on_home: e.target.checked,
                                    }))
                                }
                            />
                        </div>
                    </div>
                </div>

                <div className="wp-quick-edit-actions">
                    <Button
                        variant="primary"
                        size="sm"
                        disabled={quickSaving}
                        onClick={() => handleSaveQuickEdit(r.id)}
                    >
                        {quickSaving
                            ? 'Сохранение…'
                            : needsApproval
                              ? 'Отправить на согласование'
                              : 'Обновить'}
                    </Button>
                    <Button
                        variant="secondary"
                        size="sm"
                        disabled={quickSaving}
                        onClick={cancelQuickEdit}
                    >
                        Отмена
                    </Button>
                </div>
            </div>
        );
    };

    return (
        <>
            <Head title="Новости" />
            <PageHeader
                title="Новости и заявления"
                subtitle="Официальные новости и пресс-релизы Комитета · публикуются на сайте после согласования"
                actions={
                    can('news.create') && (
                        <LinkButton
                            href={NewsController.create.url()}
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
                onChange={changeView}
                trailing={
                    trash_count > 0 && (
                        <TrashLink type="news" count={trash_count} />
                    )
                }
            />

            <FilterBar>
                <SearchInput
                    placeholder="Поиск по заголовку…"
                    defaultValue={filters.search}
                    onChange={(e) => reload({ search: e.target.value })}
                />
                <Select
                    aria-label="Фильтр новостей по статусу"
                    placeholder="Статус: все"
                    value={filters.status}
                    options={options.statuses}
                    onChange={(e) => reload({ status: e.target.value })}
                    style={{ width: 'auto' }}
                />
                <Select
                    aria-label="Фильтр новостей по категории"
                    placeholder="Рубрика: все"
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
                    {newsItems.length} из {meta.total} записей
                </span>
            </FilterBar>

            <DataTable
                columns={columns}
                rows={newsItems}
                rowKey={(r) => r.id}
                {...bulk.table}
                sort={sort}
                renderSubRow={renderSubRow}
                onRowClick={(r) => router.visit(NewsController.edit.url(r.id))}
                onSortChange={(s) => reload({ sort: s.key, dir: s.dir })}
                emptyTitle="Новостей не найдено"
                emptyHint="Измените фильтры или создайте новую новость."
                emptyAction={
                    can('news.create') ? (
                        <LinkButton
                            href={NewsController.create.url()}
                            variant="secondary"
                        >
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
                    router.delete(NewsController.destroy.url(deleteTarget.id), {
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
                        ? `Новость «${unpublishTarget.title}» будет убрана с сайта и вернётся в черновики.`
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
                        NewsController.unpublish.url(unpublishTarget.id),
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
