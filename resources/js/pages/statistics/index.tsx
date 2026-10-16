import { Head, Link, router } from '@inertiajs/react';
import { ExternalLink } from 'lucide-react';
import { statistics } from '@/routes';
import { Tag } from '@/ui/Badge';
import { Blueprint } from '@/ui/Blueprint';
import { DataTable } from '@/ui/DataTable';
import type { Column } from '@/ui/DataTable';
import { MetricCard } from '@/ui/Feedback';
import { Segmented } from '@/ui/Field';
import { PageHeader } from '@/ui/PageHeader';

interface TopRow {
    type: string;
    type_label: string;
    title: string;
    views: number;
    edit_url: string;
    public_url: string | null;
}

interface Report {
    days: number;
    total: number;
    daily: { date: string; views: number }[];
    by_locale: { tj: number; ru: number; en: number };
    by_type: { type: string; label: string; views: number }[];
    top: TopRow[];
}

interface Props {
    report: Report;
    periods: number[];
}

const LOCALE_LABELS: Record<keyof Report['by_locale'], string> = {
    tj: 'Таджикский',
    ru: 'Русский',
    en: 'Английский',
};

const number = (value: number): string => value.toLocaleString('ru-RU');

const percent = (part: number, total: number): string =>
    total === 0 ? '0 %' : `${Math.round((part / total) * 100)} %`;

/**
 * «Статистика просмотров»: our own count of the site's readers (owner
 * decision, 2026-10-16 — not Google Analytics). The site counts a page once
 * per reader session and stores only daily sums: no IP, no cookies.
 */
export default function StatisticsIndex({ report, periods }: Props) {
    const peak = Math.max(1, ...report.daily.map((d) => d.views));
    const average = Math.round(report.total / report.daily.length);
    const topLocale = (
        Object.keys(report.by_locale) as (keyof Report['by_locale'])[]
    ).reduce((best, l) =>
        report.by_locale[l] > report.by_locale[best] ? l : best,
    );

    const columns: Column<TopRow>[] = [
        {
            key: 'title',
            header: 'Материал',
            render: (r) => (
                <Link
                    href={r.edit_url}
                    style={{
                        fontWeight: 600,
                        color: 'var(--color-text)',
                        textDecoration: 'none',
                    }}
                >
                    {r.title}
                </Link>
            ),
        },
        {
            key: 'type',
            header: 'Тип',
            width: 150,
            optional: 2,
            render: (r) => <Tag tone="neutral">{r.type_label}</Tag>,
        },
        {
            key: 'views',
            header: 'Просмотры',
            width: 120,
            align: 'right',
            render: (r) => (
                <span className="ui-mono" style={{ fontWeight: 600 }}>
                    {number(r.views)}
                </span>
            ),
        },
        {
            key: 'site',
            header: <span className="sr-only">На сайте</span>,
            width: 56,
            render: (r) =>
                r.public_url ? (
                    <a
                        href={r.public_url}
                        target="_blank"
                        rel="noreferrer"
                        title="Открыть на сайте"
                        aria-label={`Открыть на сайте: ${r.title}`}
                        style={{ color: 'var(--color-neutral-600)' }}
                    >
                        <ExternalLink size={15} strokeWidth={1.5} />
                    </a>
                ) : null,
        },
    ];

    return (
        <>
            <Head title="Статистика просмотров" />
            <PageHeader
                title="Статистика просмотров"
                subtitle="Сколько раз граждане открывали страницы сайта. Один читатель за сеанс считается один раз; хранятся только суммы по дням — без IP-адресов и cookie."
                actions={
                    <Segmented
                        value={String(report.days)}
                        onChange={(days) =>
                            router.get(
                                statistics.url(),
                                { days },
                                { preserveScroll: true },
                            )
                        }
                        options={periods.map((p) => ({
                            value: String(p),
                            label: `${p} дней`,
                        }))}
                    />
                }
            />

            <div
                className="cms-metrics-grid"
                style={{
                    display: 'grid',
                    gridTemplateColumns: 'repeat(3, 1fr)',
                    gap: 12,
                    marginBottom: 20,
                }}
            >
                <MetricCard
                    value={number(report.total)}
                    label={`просмотров за ${report.days} дней`}
                    tone="accent"
                />
                <MetricCard value={number(average)} label="в среднем за день" />
                <MetricCard
                    value={percent(report.by_locale[topLocale], report.total)}
                    label={`читают на языке: ${LOCALE_LABELS[topLocale].toLowerCase()}`}
                />
            </div>

            <section aria-labelledby="stats-daily" style={{ marginBottom: 20 }}>
                <h2 id="stats-daily" className="ui-kicker">
                    По дням
                </h2>
                <Blueprint style={{ padding: 16 }}>
                    {report.total === 0 ? (
                        <p
                            style={{
                                margin: 0,
                                fontSize: 13,
                                color: 'var(--color-neutral-600)',
                            }}
                        >
                            Просмотров за этот период пока нет. Они появятся,
                            когда граждане откроют страницы сайта.
                        </p>
                    ) : (
                        <ol
                            aria-label="Просмотры по дням"
                            style={{
                                display: 'flex',
                                alignItems: 'flex-end',
                                gap: 2,
                                height: 140,
                                margin: 0,
                                padding: 0,
                                listStyle: 'none',
                            }}
                        >
                            {report.daily.map((d) => (
                                <li
                                    key={d.date}
                                    title={`${formatDay(d.date)}: ${number(d.views)}`}
                                    style={{
                                        flex: 1,
                                        minWidth: 2,
                                        height: `${Math.max(2, (d.views / peak) * 100)}%`,
                                        background: 'var(--brand-600)',
                                        borderRadius: '2px 2px 0 0',
                                        opacity: d.views === 0 ? 0.25 : 1,
                                    }}
                                >
                                    <span className="sr-only">
                                        {formatDay(d.date)}: {number(d.views)}
                                    </span>
                                </li>
                            ))}
                        </ol>
                    )}
                </Blueprint>
            </section>

            <div
                className="cms-two-col"
                style={{
                    display: 'grid',
                    gridTemplateColumns: '1fr 1fr',
                    gap: 20,
                    marginBottom: 20,
                }}
            >
                <Breakdown
                    title="По языку сайта"
                    total={report.total}
                    rows={(
                        Object.keys(
                            LOCALE_LABELS,
                        ) as (keyof Report['by_locale'])[]
                    ).map((l) => ({
                        key: l,
                        label: LOCALE_LABELS[l],
                        views: report.by_locale[l],
                    }))}
                />
                <Breakdown
                    title="По разделам"
                    total={report.total}
                    rows={report.by_type.map((t) => ({
                        key: t.type,
                        label: t.label,
                        views: t.views,
                    }))}
                />
            </div>

            <section aria-labelledby="stats-top">
                <h2 id="stats-top" className="ui-kicker">
                    Самое читаемое за {report.days} дней
                </h2>
                <DataTable
                    columns={columns}
                    rows={report.top}
                    rowKey={(r) => r.edit_url}
                    emptyTitle="Пока нечего показать"
                    emptyHint="Здесь появятся материалы, которые граждане открывают чаще всего."
                />
            </section>
        </>
    );
}

function Breakdown({
    title,
    total,
    rows,
}: {
    title: string;
    total: number;
    rows: { key: string; label: string; views: number }[];
}) {
    return (
        <section aria-label={title}>
            <h2 className="ui-kicker">{title}</h2>
            <Blueprint style={{ padding: '6px 16px' }}>
                {rows.map((r, i) => (
                    <div
                        key={r.key}
                        style={{
                            display: 'grid',
                            gridTemplateColumns: 'minmax(0,1fr) auto 48px',
                            alignItems: 'center',
                            gap: 12,
                            padding: '9px 0',
                            borderBottom:
                                i < rows.length - 1
                                    ? '1px solid var(--color-divider)'
                                    : 0,
                            fontSize: 13,
                        }}
                    >
                        <span>{r.label}</span>
                        <span className="ui-mono" style={{ fontWeight: 600 }}>
                            {number(r.views)}
                        </span>
                        <span
                            style={{
                                textAlign: 'right',
                                color: 'var(--color-neutral-500)',
                            }}
                        >
                            {percent(r.views, total)}
                        </span>
                    </div>
                ))}
            </Blueprint>
        </section>
    );
}

function formatDay(date: string): string {
    return new Date(`${date}T00:00:00`).toLocaleDateString('ru-RU', {
        day: 'numeric',
        month: 'long',
    });
}
