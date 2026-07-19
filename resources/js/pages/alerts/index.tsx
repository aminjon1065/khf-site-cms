import { Head, Link, router } from '@inertiajs/react';
import {
    Copy,
    Eye,
    History,
    MoreVertical,
    Plus,
    SquareArrowOutUpRight,
} from 'lucide-react';
import { useState } from 'react';
import { useCan } from '@/lib/auth';
import type { ContentStatus, Severity } from '@/lib/domain';
import { useT } from '@/lib/i18n';
import { LanguageBadges, SeverityBadge, StatusBadge } from '@/ui/Badge';
import { IconButton, LinkButton } from '@/ui/Button';
import { DataTable, Pagination } from '@/ui/DataTable';
import type { Column, SortState } from '@/ui/DataTable';
import { Select } from '@/ui/Field';
import { FilterBar, SavedViews, SearchInput } from '@/ui/Filters';
import { ConfirmDialog, Dropdown } from '@/ui/Overlay';
import { PageHeader } from '@/ui/PageHeader';

interface AlertRow {
    id: number;
    title: string;
    hazard_label: string;
    severity: Severity;
    status: ContentStatus;
    regions: { name: string }[];
    languages: Record<string, number>;
    author: string | null;
    published_at: string | null;
    ends_at: string | null;
}

interface Option {
    value: string;
    label: string;
}

interface Props {
    alerts: AlertRow[];
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
        severity: string;
        status: string;
        region: string;
        hazard: string;
        sort: string;
        dir: string;
    };
    savedViews: { key: string; label: string; count: number }[];
    options: {
        severities: Option[];
        statuses: Option[];
        hazards: Option[];
        regions: Option[];
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
function fmtShort(date: string | null): string {
    return date
        ? new Date(date).toLocaleString('ru-RU', {
              day: '2-digit',
              month: '2-digit',
              hour: '2-digit',
              minute: '2-digit',
          })
        : '—';
}

export default function AlertsIndex({
    alerts,
    meta,
    filters,
    savedViews,
    options,
}: Props) {
    const { t } = useT();
    const can = useCan();
    const [unpublishTarget, setUnpublishTarget] = useState<AlertRow | null>(
        null,
    );
    const [processing, setProcessing] = useState(false);

    const reload = (patch: Partial<Props['filters']>) => {
        router.get(
            '/alerts',
            { ...filters, ...patch },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const sort: SortState | null = filters.sort
        ? { key: filters.sort, dir: filters.dir === 'asc' ? 'asc' : 'desc' }
        : null;

    const columns: Column<AlertRow>[] = [
        {
            key: 'severity',
            header: t('common.severity'),
            width: 130,
            render: (r) => <SeverityBadge severity={r.severity} />,
        },
        {
            key: 'title',
            header: t('common.title'),
            render: (r) => (
                <div style={{ minWidth: 0 }}>
                    <Link
                        href={`/alerts/${r.id}/edit`}
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
                        {r.hazard_label}
                    </div>
                </div>
            ),
        },
        {
            key: 'regions',
            header: t('common.regions'),
            width: 160,
            render: (r) => (
                <span style={{ fontSize: 12.5 }}>
                    {r.regions.map((x) => x.name).join(', ') || 'Вся страна'}
                </span>
            ),
        },
        {
            key: 'status',
            header: t('common.status'),
            width: 150,
            sortable: true,
            render: (r) => <StatusBadge status={r.status} />,
        },
        {
            key: 'languages',
            header: t('common.languages'),
            width: 170,
            render: (r) => <LanguageBadges completeness={r.languages} />,
        },
        {
            key: 'author',
            header: t('common.author'),
            width: 120,
            render: (r) => (
                <span style={{ fontSize: 12.5 }}>{r.author ?? '—'}</span>
            ),
        },
        {
            key: 'published',
            header: t('common.published'),
            width: 140,
            sortable: true,
            render: (r) => (
                <span style={{ fontSize: 12.5 }} className="ui-mono">
                    {fmt(r.published_at)}
                </span>
            ),
        },
        {
            key: 'ends',
            header: t('common.deadline'),
            width: 120,
            sortable: true,
            render: (r) => (
                <span style={{ fontSize: 12.5 }} className="ui-mono">
                    {fmtShort(r.ends_at)}
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
                            label={t('common.actions')}
                            onClick={toggle}
                            variant="ghost"
                        >
                            <MoreVertical size={17} strokeWidth={1.5} />
                        </IconButton>
                    )}
                    items={[
                        {
                            label: t('action.open'),
                            icon: (
                                <SquareArrowOutUpRight
                                    size={15}
                                    strokeWidth={1.5}
                                />
                            ),
                            onSelect: () =>
                                router.visit(`/alerts/${r.id}/edit`),
                        },
                        {
                            label: t('action.preview'),
                            icon: <Eye size={15} strokeWidth={1.5} />,
                            onSelect: () =>
                                window.open('https://khf.tj', '_blank'),
                        },
                        ...(can('alerts.create')
                            ? [
                                  {
                                      label: t('action.duplicate'),
                                      icon: (
                                          <Copy size={15} strokeWidth={1.5} />
                                      ),
                                      onSelect: () =>
                                          router.post(
                                              `/alerts/${r.id}/duplicate`,
                                          ),
                                  },
                              ]
                            : []),
                        {
                            label: t('action.history'),
                            icon: <History size={15} strokeWidth={1.5} />,
                            onSelect: () => router.visit('/activity'),
                        },
                        ...(can('alerts.publish') &&
                        (r.status === 'published' || r.status === 'updated')
                            ? [
                                  { separator: true },
                                  {
                                      label: t('action.unpublish') + '…',
                                      danger: true,
                                      onSelect: () => setUnpublishTarget(r),
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
            <Head title={t('nav.alerts')} />
            <PageHeader
                title="Предупреждения"
                subtitle="Официальные предупреждения населению · публикуются на сайте, в приложении SOS и по каналам оповещения"
                actions={
                    <>
                        <LinkButton href="/activity" variant="secondary">
                            История изменений
                        </LinkButton>
                        {can('alerts.create') && (
                            <LinkButton
                                href="/alerts/create"
                                variant="primary"
                                icon={<Plus size={16} strokeWidth={2} />}
                            >
                                Создать предупреждение
                            </LinkButton>
                        )}
                    </>
                }
            />

            <SavedViews
                views={savedViews.map((v) => ({
                    key: v.key,
                    label: v.label,
                    count: v.count,
                }))}
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
                    placeholder="Уровень: любой"
                    value={filters.severity}
                    options={options.severities}
                    onChange={(e) => reload({ severity: e.target.value })}
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
                    placeholder="Регион: все"
                    value={filters.region}
                    options={options.regions}
                    onChange={(e) => reload({ region: e.target.value })}
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
                    {alerts.length} из {meta.total} записей
                </span>
            </FilterBar>

            <DataTable
                columns={columns}
                rows={alerts}
                rowKey={(r) => r.id}
                sort={sort}
                onSortChange={(s) => reload({ sort: s.key, dir: s.dir })}
                emptyTitle="Предупреждений не найдено"
                emptyHint="Измените фильтры или создайте новое предупреждение."
                emptyAction={
                    can('alerts.create') ? (
                        <LinkButton href="/alerts/create" variant="secondary">
                            Создать предупреждение
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
                open={!!unpublishTarget}
                onClose={() => setUnpublishTarget(null)}
                loading={processing}
                title="Отменить предупреждение?"
                body={
                    unpublishTarget
                        ? `Предупреждение «${unpublishTarget.title}» будет снято с сайта и из приложения SOS. Действие фиксируется в журнале.`
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
                        `/alerts/${unpublishTarget.id}/unpublish`,
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
