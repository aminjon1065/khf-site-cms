import { Head, router } from '@inertiajs/react';
import { Download, TriangleAlert } from 'lucide-react';
import { useT } from '@/lib/i18n';
import { Button } from '@/ui/Button';
import { DataTable, Pagination } from '@/ui/DataTable';
import type { Column } from '@/ui/DataTable';
import { Checkbox, Select } from '@/ui/Field';
import { FilterBar } from '@/ui/Filters';
import { PageHeader } from '@/ui/PageHeader';

interface Row {
    id: number;
    datetime: string;
    who: string;
    action: string;
    change: string;
    ip: string;
    location: string;
    section: string;
    is_critical: boolean;
}

interface Option {
    value: string;
    label: string;
}

interface Props {
    activities: Row[];
    meta: {
        from: number | null;
        to: number | null;
        total: number;
        per_page: number;
        prev: string | null;
        next: string | null;
    };
    filters: {
        user: string;
        section: string;
        period: string;
        critical: boolean;
    };
    options: { users: Option[]; sections: Option[]; periods: Option[] };
}

export default function Activity({
    activities,
    meta,
    filters,
    options,
}: Props) {
    const { t } = useT();

    const reload = (patch: Partial<Props['filters']>) => {
        router.get(
            '/activity',
            { ...filters, ...patch },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const exportCsv = () => {
        const params = new URLSearchParams({
            user: filters.user,
            section: filters.section,
            period: filters.period,
            critical: filters.critical ? '1' : '',
        });
        window.location.href = `/activity/export?${params.toString()}`;
    };

    const columns: Column<Row>[] = [
        {
            key: 'datetime',
            header: 'Дата и время',
            width: 150,
            render: (r) => (
                <span className="ui-mono" style={{ fontSize: 12.5 }}>
                    {r.datetime}
                </span>
            ),
        },
        {
            key: 'who',
            header: 'Сотрудник',
            width: 140,
            render: (r) => <span style={{ fontSize: 13 }}>{r.who}</span>,
        },
        {
            key: 'action',
            header: 'Действие',
            render: (r) => (
                <span
                    style={{
                        display: 'inline-flex',
                        alignItems: 'center',
                        gap: 6,
                    }}
                >
                    {r.is_critical && (
                        <TriangleAlert
                            size={14}
                            strokeWidth={1.5}
                            style={{ color: 'var(--danger)', flex: 'none' }}
                        />
                    )}
                    <span>{r.action}</span>
                </span>
            ),
        },
        {
            key: 'change',
            header: 'Изменение',
            width: 260,
            render: (r) => (
                <span
                    style={{
                        fontSize: 12.5,
                        color: 'var(--color-neutral-600)',
                    }}
                >
                    {r.change || '—'}
                </span>
            ),
        },
        {
            key: 'source',
            header: 'Источник',
            width: 150,
            render: (r) => (
                <span
                    style={{ fontSize: 12, color: 'var(--color-neutral-600)' }}
                >
                    <span className="ui-mono">{r.ip}</span>
                    {r.location ? ` · ${r.location}` : ''}
                </span>
            ),
        },
    ];

    return (
        <>
            <Head title={t('nav.activity')} />
            <PageHeader
                title="Журнал действий"
                subtitle="Полная история операций · записи не редактируются и хранятся 5 лет"
                actions={
                    <Button
                        variant="secondary"
                        icon={<Download size={16} strokeWidth={1.5} />}
                        onClick={exportCsv}
                    >
                        Экспорт за период
                    </Button>
                }
            />

            <FilterBar>
                <Select
                    placeholder="Сотрудник: все"
                    value={filters.user}
                    options={options.users}
                    onChange={(e) => reload({ user: e.target.value })}
                    style={{ width: 'auto' }}
                />
                <Select
                    placeholder="Раздел: все"
                    value={filters.section}
                    options={options.sections}
                    onChange={(e) => reload({ section: e.target.value })}
                    style={{ width: 'auto' }}
                />
                <Select
                    value={filters.period}
                    options={options.periods}
                    onChange={(e) => reload({ period: e.target.value })}
                    style={{ width: 'auto' }}
                />
                <Checkbox
                    label="Только критические действия"
                    checked={filters.critical}
                    onChange={(e) => reload({ critical: e.target.checked })}
                />
            </FilterBar>

            <DataTable
                columns={columns}
                rows={activities}
                rowKey={(r) => r.id}
                emptyTitle="Записей не найдено"
                emptyHint="Измените фильтры или период."
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
