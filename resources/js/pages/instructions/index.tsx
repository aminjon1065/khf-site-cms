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
import InstructionController from '@/actions/App/Http/Controllers/Cms/InstructionController';
import { useBulkActions } from '@/cms/BulkActions';
import { TrashLink } from '@/cms/TrashLink';
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

interface InstructionRow {
    id: number;
    name: string;
    slug: string | null;
    /** Null while the item isn't visible on the public site. */
    public_url: string | null;
    status: ContentStatus;
    hazard_type: string | null;
    hazard_label: string | null;
    is_priority: boolean;
    sort: number;
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
    instructions: InstructionRow[];
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
        hazard: string;
        sort: string;
        dir: string;
    };
    savedViews: { key: string; label: string; count: number }[];
    options: {
        statuses: Option[];
        hazards: Option[];
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

export default function InstructionsIndex({
    trash_count,
    instructions,
    meta,
    filters,
    savedViews,
    options,
}: Props) {
    const can = useCan();
    const bulk = useBulkActions({
        type: 'instructions',
        rows: instructions,
        rowTitle: (r) => r.name,
        allowed: {
            submit: can('instructions.edit'),
            publish: can('instructions.publish'),
            trash: can('instructions.delete'),
        },
    });
    const [deleteTarget, setDeleteTarget] = useState<InstructionRow | null>(
        null,
    );
    const [unpublishTarget, setUnpublishTarget] =
        useState<InstructionRow | null>(null);
    const [processing, setProcessing] = useState(false);

    const reload = (patch: Partial<Props['filters']>) => {
        router.get(
            InstructionController.index.url(),
            { ...filters, ...patch },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const sort: SortState | null = filters.sort
        ? { key: filters.sort, dir: filters.dir === 'asc' ? 'asc' : 'desc' }
        : null;

    const columns: Column<InstructionRow>[] = [
        {
            key: 'name',
            header: 'Название',
            render: (r) => (
                <div style={{ minWidth: 0 }}>
                    <Link
                        href={InstructionController.edit.url(r.id)}
                        style={{
                            fontWeight: 600,
                            color: 'var(--color-text)',
                            textDecoration: 'none',
                            display: 'inline-flex',
                            alignItems: 'center',
                            gap: 6,
                        }}
                    >
                        {r.is_priority && (
                            <Pin
                                size={13}
                                strokeWidth={1.75}
                                style={{ color: 'var(--brand-600)' }}
                            />
                        )}
                        {r.name}
                    </Link>
                    <div
                        style={{
                            fontSize: 11.5,
                            color: 'var(--color-neutral-500)',
                        }}
                    >
                        {r.slug ?? '—'}
                    </div>
                    <div className="wp-row-actions">
                        <Link
                            href={InstructionController.edit.url(r.id)}
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
            key: 'hazard',
            header: 'Тип события',
            width: 160,
            render: (r) =>
                r.hazard_label ? (
                    <Tag tone="outline">{r.hazard_label}</Tag>
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
            optional: 2,
            render: (r) => <LanguageBadges completeness={r.languages} />,
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
            width: 120,
            optional: 1,
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
                                router.visit(
                                    InstructionController.edit.url(r.id),
                                ),
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
                        ...(can('instructions.create')
                            ? [
                                  {
                                      label: 'Дублировать',
                                      icon: (
                                          <Copy size={15} strokeWidth={1.5} />
                                      ),
                                      onSelect: () =>
                                          router.post(
                                              InstructionController.duplicate.url(
                                                  r.id,
                                              ),
                                          ),
                                  },
                              ]
                            : []),
                        ...(can('instructions.publish') &&
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
                        ...(can('instructions.delete')
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
            <Head title="Инструкции населению" />
            <PageHeader
                title="Инструкции населению"
                subtitle="Пошаговые памятки: как действовать до, во время и после чрезвычайных ситуаций"
                actions={
                    can('instructions.create') && (
                        <LinkButton
                            href={InstructionController.create.url()}
                            variant="primary"
                            icon={<Plus size={16} strokeWidth={2} />}
                        >
                            Создать инструкцию
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
                        <TrashLink type="instructions" count={trash_count} />
                    )
                }
            />

            <FilterBar>
                <SearchInput
                    placeholder="Поиск по названию…"
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
                    placeholder="Тип события: все"
                    value={filters.hazard}
                    options={options.hazards}
                    onChange={(e) => reload({ hazard: e.target.value })}
                    style={{ width: 'auto' }}
                />
                <span
                    style={{
                        marginLeft: 'auto',
                        fontSize: 12.5,
                        color: 'var(--color-neutral-600)',
                    }}
                >
                    {instructions.length} из {meta.total} записей
                </span>
            </FilterBar>

            <DataTable
                columns={columns}
                rows={instructions}
                rowKey={(r) => r.id}
                {...bulk.table}
                sort={sort}
                onRowClick={(r) =>
                    router.visit(InstructionController.edit.url(r.id))
                }
                onSortChange={(s) => reload({ sort: s.key, dir: s.dir })}
                emptyTitle="Инструкций не найдено"
                emptyHint="Измените фильтры или создайте новую инструкцию."
                emptyAction={
                    can('instructions.create') ? (
                        <LinkButton
                            href={InstructionController.create.url()}
                            variant="secondary"
                        >
                            Создать инструкцию
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
                title="Удалить инструкцию?"
                body={
                    deleteTarget
                        ? `Инструкция «${deleteTarget.name}» будет перемещена в корзину.`
                        : ''
                }
                confirmLabel="Удалить"
                onConfirm={() => {
                    if (!deleteTarget) {
                        return;
                    }

                    setProcessing(true);
                    router.delete(
                        InstructionController.destroy.url(deleteTarget.id),
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

            <ConfirmDialog
                open={!!unpublishTarget}
                onClose={() => setUnpublishTarget(null)}
                loading={processing}
                title="Снять инструкцию с публикации?"
                body={
                    unpublishTarget
                        ? `Инструкция «${unpublishTarget.name}» будет убрана с сайта и вернётся в черновики.`
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
                        InstructionController.unpublish.url(unpublishTarget.id),
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
