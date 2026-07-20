import { Head, Link, router } from '@inertiajs/react';
import {
    Copy,
    Download,
    MoreVertical,
    Plus,
    SquareArrowOutUpRight,
    Trash2,
} from 'lucide-react';
import { useState } from 'react';
import { useCan } from '@/lib/auth';
import type { ContentStatus } from '@/lib/domain';
import { StatusBadge, Tag } from '@/ui/Badge';
import { IconButton, LinkButton } from '@/ui/Button';
import { DataTable, Pagination } from '@/ui/DataTable';
import type { Column, SortState } from '@/ui/DataTable';
import { Select } from '@/ui/Field';
import { FilterBar, SavedViews, SearchInput } from '@/ui/Filters';
import { ConfirmDialog, Dropdown } from '@/ui/Overlay';
import { PageHeader } from '@/ui/PageHeader';

interface DocumentRow {
    id: number;
    name: string;
    doc_type: string;
    doc_type_label: string;
    number: string | null;
    section: string | null;
    status: ContentStatus;
    files: { tg: boolean; ru: boolean; en: boolean };
    has_file: boolean;
    author: string | null;
    doc_date: string | null;
    published_at: string | null;
}

interface Option {
    value: string;
    label: string;
}

interface Props {
    documents: DocumentRow[];
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
        type: string;
        section: string;
        sort: string;
        dir: string;
    };
    savedViews: { key: string; label: string; count: number }[];
    options: {
        statuses: Option[];
        types: Option[];
        sections: string[];
    };
}

const LANG_LABEL: Record<'tg' | 'ru' | 'en', string> = {
    tg: 'ТҶ',
    ru: 'РУ',
    en: 'EN',
};

function fmt(date: string | null): string {
    return date
        ? new Date(date).toLocaleDateString('ru-RU', {
              day: '2-digit',
              month: '2-digit',
              year: 'numeric',
          })
        : '—';
}

function FileLangs({ files }: { files: DocumentRow['files'] }) {
    return (
        <span style={{ display: 'inline-flex', gap: 6 }} className="ui-mono">
            {(['tg', 'ru', 'en'] as const).map((l) => (
                <span
                    key={l}
                    style={{
                        fontSize: 11.5,
                        color: files[l]
                            ? 'var(--color-text)'
                            : 'var(--color-neutral-400)',
                        fontWeight: files[l] ? 600 : 400,
                    }}
                >
                    {LANG_LABEL[l]}
                </span>
            ))}
        </span>
    );
}

export default function DocumentsIndex({
    documents,
    meta,
    filters,
    savedViews,
    options,
}: Props) {
    const can = useCan();
    const [deleteTarget, setDeleteTarget] = useState<DocumentRow | null>(null);
    const [unpublishTarget, setUnpublishTarget] = useState<DocumentRow | null>(
        null,
    );
    const [processing, setProcessing] = useState(false);

    const reload = (patch: Partial<Props['filters']>) => {
        router.get(
            '/documents',
            { ...filters, ...patch },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const sort: SortState | null = filters.sort
        ? { key: filters.sort, dir: filters.dir === 'asc' ? 'asc' : 'desc' }
        : null;

    const columns: Column<DocumentRow>[] = [
        {
            key: 'type',
            header: 'Тип',
            width: 130,
            sortable: true,
            render: (r) => <Tag tone="neutral">{r.doc_type_label}</Tag>,
        },
        {
            key: 'name',
            header: 'Название',
            render: (r) => (
                <div style={{ minWidth: 0 }}>
                    <Link
                        href={`/documents/${r.id}/edit`}
                        style={{
                            fontWeight: 600,
                            color: 'var(--color-text)',
                            textDecoration: 'none',
                        }}
                    >
                        {r.name}
                    </Link>
                    {r.section && (
                        <div
                            style={{
                                fontSize: 11.5,
                                color: 'var(--color-neutral-500)',
                            }}
                        >
                            {r.section}
                        </div>
                    )}
                </div>
            ),
        },
        {
            key: 'number',
            header: 'Номер',
            width: 110,
            render: (r) => (
                <span style={{ fontSize: 12.5 }} className="ui-mono">
                    {r.number ?? '—'}
                </span>
            ),
        },
        {
            key: 'date',
            header: 'Дата',
            width: 110,
            sortable: true,
            render: (r) => (
                <span style={{ fontSize: 12.5 }} className="ui-mono">
                    {fmt(r.doc_date)}
                </span>
            ),
        },
        {
            key: 'files',
            header: 'Файлы',
            width: 110,
            render: (r) =>
                r.has_file ? (
                    <FileLangs files={r.files} />
                ) : (
                    <span
                        style={{
                            fontSize: 12,
                            color: 'var(--warn)',
                            display: 'inline-flex',
                            alignItems: 'center',
                            gap: 4,
                        }}
                    >
                        нет файла
                    </span>
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
                                router.visit(`/documents/${r.id}/edit`),
                        },
                        {
                            label: 'Открыть на сайте',
                            icon: <Download size={15} strokeWidth={1.5} />,
                            onSelect: () =>
                                window.open(
                                    'https://khf.tj/documents',
                                    '_blank',
                                ),
                        },
                        ...(can('documents.create')
                            ? [
                                  {
                                      label: 'Дублировать',
                                      icon: <Copy size={15} strokeWidth={1.5} />,
                                      onSelect: () =>
                                          router.post(
                                              `/documents/${r.id}/duplicate`,
                                          ),
                                  },
                              ]
                            : []),
                        ...(can('documents.publish') &&
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
                        ...(can('documents.delete')
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
            <Head title="Документы" />
            <PageHeader
                title="Документы"
                subtitle="Нормативные акты, отчёты и памятки · публикуются с файлами на трёх языках"
                actions={
                    can('documents.create') && (
                        <LinkButton
                            href="/documents/create"
                            variant="primary"
                            icon={<Plus size={16} strokeWidth={2} />}
                        >
                            Добавить документ
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
                    placeholder="Название или номер…"
                    defaultValue={filters.search}
                    onChange={(e) => reload({ search: e.target.value })}
                />
                <Select
                    placeholder="Тип: все"
                    value={filters.type}
                    options={options.types}
                    onChange={(e) => reload({ type: e.target.value })}
                    style={{ width: 'auto' }}
                />
                <Select
                    placeholder="Статус: все"
                    value={filters.status}
                    options={options.statuses}
                    onChange={(e) => reload({ status: e.target.value })}
                    style={{ width: 'auto' }}
                />
                <Select
                    placeholder="Раздел: все"
                    value={filters.section}
                    options={options.sections.map((s) => ({
                        value: s,
                        label: s,
                    }))}
                    onChange={(e) => reload({ section: e.target.value })}
                    style={{ width: 'auto' }}
                />
                <span
                    style={{
                        marginLeft: 'auto',
                        fontSize: 12.5,
                        color: 'var(--color-neutral-600)',
                    }}
                >
                    {documents.length} из {meta.total} записей
                </span>
            </FilterBar>

            <DataTable
                columns={columns}
                rows={documents}
                rowKey={(r) => r.id}
                sort={sort}
                onSortChange={(s) => reload({ sort: s.key, dir: s.dir })}
                emptyTitle="Документов не найдено"
                emptyHint="Измените фильтры или добавьте новый документ."
                emptyAction={
                    can('documents.create') ? (
                        <LinkButton
                            href="/documents/create"
                            variant="secondary"
                        >
                            Добавить документ
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
                title="Удалить документ?"
                body={
                    deleteTarget
                        ? `Документ «${deleteTarget.name}» и его файлы будут перемещены в корзину.`
                        : ''
                }
                confirmLabel="Удалить"
                onConfirm={() => {
                    if (!deleteTarget) {
                        return;
                    }

                    setProcessing(true);
                    router.delete(`/documents/${deleteTarget.id}`, {
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
                title="Снять документ с публикации?"
                body={
                    unpublishTarget
                        ? `Документ «${unpublishTarget.name}» будет убран с сайта и отправлен в архив.`
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
                        `/documents/${unpublishTarget.id}/unpublish`,
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
