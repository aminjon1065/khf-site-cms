import { Head, router } from '@inertiajs/react';
import { Languages, Pencil } from 'lucide-react';
import TranslationQueueController from '@/actions/App/Http/Controllers/Cms/TranslationQueueController';
import { LanguageBadges, Tag } from '@/ui/Badge';
import { LinkButton } from '@/ui/Button';
import { DataTable, Pagination } from '@/ui/DataTable';
import type { Column } from '@/ui/DataTable';
import { Select } from '@/ui/Field';
import { FilterBar } from '@/ui/Filters';
import { PageHeader } from '@/ui/PageHeader';

interface TranslationItem {
    key: string;
    id: number;
    type: string;
    type_label: string;
    title: string;
    status: string;
    languages: Record<'tg' | 'ru' | 'en', number>;
    missing_locales: ('tg' | 'ru' | 'en')[];
    updated_at: string | null;
    edit_url: string;
}

interface Props {
    items: TranslationItem[];
    meta: {
        from: number | null;
        to: number | null;
        total: number;
        prev: string | null;
        next: string | null;
    };
    filters: { type: string; locale: string };
    types: { value: string; label: string }[];
    locales: { value: string; label: string }[];
}

const localeNames: Record<'tg' | 'ru' | 'en', string> = {
    tg: 'Тоҷикӣ',
    ru: 'Русский',
    en: 'English',
};

export default function TranslationQueue({
    items,
    meta,
    filters,
    types,
    locales,
}: Props) {
    const filter = (next: Partial<Props['filters']>) => {
        router.get(
            TranslationQueueController.index.url(),
            { ...filters, ...next },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const columns: Column<TranslationItem>[] = [
        {
            key: 'title',
            header: 'Материал',
            render: (item) => (
                <div style={{ minWidth: 230 }}>
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
            header: 'Workflow',
            width: 150,
            render: (item) => (
                <Tag
                    tone={
                        item.status === 'Проверка переводов'
                            ? 'warn'
                            : 'neutral'
                    }
                >
                    {item.status}
                </Tag>
            ),
        },
        {
            key: 'languages',
            header: 'Полнота',
            width: 260,
            render: (item) => <LanguageBadges completeness={item.languages} />,
        },
        {
            key: 'missing',
            header: 'Нужен перевод',
            width: 180,
            render: (item) =>
                item.missing_locales
                    .map((locale) => localeNames[locale])
                    .join(', '),
        },
        {
            key: 'updated_at',
            header: 'Обновлён',
            width: 145,
            render: (item) => (
                <span className="ui-mono" style={{ fontSize: 12.5 }}>
                    {item.updated_at ?? '—'}
                </span>
            ),
        },
        {
            key: 'actions',
            header: '',
            width: 135,
            align: 'right',
            render: (item) => (
                <LinkButton
                    href={item.edit_url}
                    size="sm"
                    variant="secondary"
                    icon={<Pencil size={15} aria-hidden />}
                >
                    Перевести
                </LinkButton>
            ),
        },
    ];

    return (
        <>
            <Head title="Очередь переводов" />
            <PageHeader
                eyebrow="Редакционные материалы"
                title="Очередь переводов"
                subtitle="Неполные локали собраны в одном месте. Сначала показаны материалы на проверке переводов и давно не обновлявшиеся записи."
            />

            <FilterBar>
                <Select
                    aria-label="Фильтр очереди переводов по типу материала"
                    placeholder="Все типы материалов"
                    value={filters.type}
                    options={types}
                    onChange={(event) => filter({ type: event.target.value })}
                    style={{ width: 'auto' }}
                />
                <Select
                    aria-label="Фильтр очереди по языку"
                    placeholder="Все языки"
                    value={filters.locale}
                    options={locales}
                    onChange={(event) => filter({ locale: event.target.value })}
                    style={{ width: 'auto' }}
                />
            </FilterBar>

            <DataTable
                columns={columns}
                rows={items}
                rowKey={(item) => item.key}
                emptyTitle="Переводы завершены"
                emptyHint="Для выбранных фильтров нет материалов с незаполненными обязательными полями."
                emptyAction={<Languages size={18} aria-hidden />}
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
