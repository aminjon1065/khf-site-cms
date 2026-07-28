import { Head, router } from '@inertiajs/react';
import { ArchiveRestore, Trash2 } from 'lucide-react';
import EditorialTrashController from '@/actions/App/Http/Controllers/Cms/EditorialTrashController';
import { Tag } from '@/ui/Badge';
import { Button } from '@/ui/Button';
import { DataTable, Pagination } from '@/ui/DataTable';
import type { Column } from '@/ui/DataTable';
import { Select } from '@/ui/Field';
import { FilterBar } from '@/ui/Filters';
import { PageHeader } from '@/ui/PageHeader';

interface TrashItem {
    key: string;
    id: number;
    type: string;
    type_label: string;
    title: string;
    status: string;
    author: string;
    deleted_at: string;
    can_restore: boolean;
}

interface Props {
    items: TrashItem[];
    meta: {
        from: number | null;
        to: number | null;
        total: number;
        prev: string | null;
        next: string | null;
    };
    filters: { type: string };
    types: { value: string; label: string }[];
}

export default function EditorialTrash({ items, meta, filters, types }: Props) {
    const filterByType = (type: string) => {
        router.get(
            EditorialTrashController.index.url(),
            { type },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const restore = (item: TrashItem) => {
        if (
            !item.can_restore ||
            !window.confirm(`Восстановить «${item.title}» из корзины?`)
        ) {
            return;
        }

        router.post(
            EditorialTrashController.restore.url({
                contentType: item.type,
                contentId: item.id,
            }),
        );
    };

    const columns: Column<TrashItem>[] = [
        {
            key: 'title',
            header: 'Материал',
            render: (item) => (
                <div style={{ minWidth: 220 }}>
                    <strong style={{ display: 'block' }}>{item.title}</strong>
                    <span
                        style={{
                            fontSize: 12,
                            color: 'var(--color-neutral-600)',
                        }}
                    >
                        {item.type_label}
                    </span>
                </div>
            ),
        },
        {
            key: 'status',
            header: 'Статус до удаления',
            width: 160,
            render: (item) => <Tag tone="neutral">{item.status}</Tag>,
        },
        {
            key: 'author',
            header: 'Автор',
            width: 160,
            render: (item) => item.author,
        },
        {
            key: 'deleted_at',
            header: 'Удалён',
            width: 150,
            render: (item) => (
                <span className="ui-mono" style={{ fontSize: 12.5 }}>
                    {item.deleted_at}
                </span>
            ),
        },
        {
            key: 'actions',
            header: '',
            width: 150,
            align: 'right',
            render: (item) =>
                item.can_restore ? (
                    <Button
                        size="sm"
                        variant="secondary"
                        icon={<ArchiveRestore size={15} strokeWidth={1.75} />}
                        onClick={() => restore(item)}
                    >
                        Восстановить
                    </Button>
                ) : (
                    <span
                        style={{
                            fontSize: 12,
                            color: 'var(--color-neutral-600)',
                        }}
                    >
                        Только просмотр
                    </span>
                ),
        },
    ];

    return (
        <>
            <Head title="Корзина материалов" />
            <PageHeader
                eyebrow="Редакционные материалы"
                title="Корзина материалов"
                subtitle="Удалённые новости, страницы, проекты, инструкции, объявления и документы можно безопасно восстановить."
            />

            <FilterBar>
                <Select
                    aria-label="Фильтр корзины по типу материала"
                    placeholder="Все типы материалов"
                    value={filters.type}
                    options={types}
                    onChange={(event) => filterByType(event.target.value)}
                    style={{ width: 'auto' }}
                />
            </FilterBar>

            <DataTable
                columns={columns}
                rows={items}
                rowKey={(item) => item.key}
                emptyTitle="Корзина пуста"
                emptyHint="Удалённые редакционные материалы появятся здесь и сохранят свой workflow-статус."
                emptyAction={<Trash2 size={18} aria-hidden />}
            />

            <Pagination
                from={meta.from ?? 0}
                to={meta.to ?? 0}
                total={meta.total}
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
        </>
    );
}
