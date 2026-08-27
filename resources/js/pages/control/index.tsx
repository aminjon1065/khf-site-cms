import { Head } from '@inertiajs/react';
import {
    Clock3,
    Gauge,
    MapPinned,
    MonitorSmartphone,
    Radio,
    Siren,
} from 'lucide-react';
import type { Severity } from '@/lib/domain';
import { SeverityBadge, Tag } from '@/ui/Badge';
import { Blueprint } from '@/ui/Blueprint';
import { LinkButton } from '@/ui/Button';
import { EmptyState } from '@/ui/Feedback';
import { PageHeader } from '@/ui/PageHeader';

interface RegionStatus {
    key: string;
    name: string;
    level: string;
    count: number;
    statusText: string;
}

interface ActiveAlert {
    id: number;
    title: string;
    severity: Severity;
    regions: string;
    ends_at: string | null;
    url: string;
}

interface Props {
    state: string;
    regions: RegionStatus[];
    metrics: {
        active: number;
        critical: number;
        ending_soon: number;
        affected_regions: number;
    };
    alerts: ActiveAlert[];
    web_vitals: WebVitalsReport;
    operations: OperationalReport;
}

type VitalRating = 'good' | 'needs-improvement' | 'poor' | 'no-data';

interface VitalMetric {
    metric: 'LCP' | 'INP' | 'CLS';
    p75: number | null;
    samples: number;
    rating: VitalRating;
    provisional: boolean;
}

interface VitalDimension {
    route?: string;
    device?: string;
    samples: number;
    metrics: VitalMetric[];
}

interface WebVitalsReport {
    period_days: number;
    since: string;
    total_samples: number;
    metrics: VitalMetric[];
    routes: VitalDimension[];
    devices: VitalDimension[];
}

interface SampleWindow {
    from: string | null;
    to: string | null;
}

interface OperationalReport {
    api: {
        samples: number;
        client_errors: number;
        server_errors: number;
        p95_ms: number | null;
        window: SampleWindow;
        routes: {
            route: string;
            samples: number;
            client_errors: number;
            server_errors: number;
            p95_ms: number | null;
        }[];
    };
    queue: {
        samples: number;
        failures: number;
        p95_ms: number | null;
        window: SampleWindow;
        last_processed_at: string | null;
    };
    cache: {
        hits: number;
        misses: number;
        partial: number;
        requests: number;
        hit_rate: number | null;
    };
}

const levelTone: Record<string, 'neutral' | 'accent' | 'warn' | 'danger'> = {
    none: 'neutral',
    info: 'accent',
    warning: 'warn',
    danger: 'danger',
    critical: 'danger',
};

const ratingTone: Record<VitalRating, 'neutral' | 'ok' | 'warn' | 'danger'> = {
    good: 'ok',
    'needs-improvement': 'warn',
    poor: 'danger',
    'no-data': 'neutral',
};

const ratingLabel: Record<VitalRating, string> = {
    good: 'Хорошо',
    'needs-improvement': 'Нужно улучшить',
    poor: 'Плохо',
    'no-data': 'Нет данных',
};

/**
 * Период, который покрывает выборка. Без него «p95 = 400 мс» невозможно
 * прочитать: двести замеров могли уложиться в минуту пиковой нагрузки или
 * растянуться на три дня.
 */
function sampleWindow(window: SampleWindow): string {
    if (!window.from || !window.to) {
        return 'Данных пока нет';
    }

    const format = (value: string) =>
        new Date(value).toLocaleString('ru-RU', {
            day: 'numeric',
            month: 'short',
            hour: '2-digit',
            minute: '2-digit',
        });

    return `Выборка за ${format(window.from)} — ${format(window.to)}`;
}

function formatVital(metric: VitalMetric): string {
    if (metric.p75 === null) {
        return '—';
    }

    return metric.metric === 'CLS'
        ? metric.p75.toFixed(3)
        : `${Math.round(metric.p75)} мс`;
}

export default function ControlCenter({
    state,
    regions,
    metrics,
    alerts,
    web_vitals: webVitals,
    operations,
}: Props) {
    const cards = [
        {
            label: 'Активных предупреждений',
            value: metrics.active,
            icon: Radio,
        },
        { label: 'Критических', value: metrics.critical, icon: Siren },
        {
            label: 'Истекают в течение суток',
            value: metrics.ending_soon,
            icon: Clock3,
        },
        {
            label: 'Затронуто регионов',
            value: metrics.affected_regions,
            icon: MapPinned,
        },
    ];

    return (
        <>
            <Head title="Центр контроля" />
            <PageHeader
                title="Центр контроля"
                subtitle="Оперативная обстановка по действующим предупреждениям и регионам"
                actions={
                    <Tag tone={state === 'calm' ? 'ok' : 'danger'}>
                        {state === 'calm'
                            ? 'Обстановка штатная'
                            : 'Требуется внимание'}
                    </Tag>
                }
            />

            <div className="mb-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                {cards.map((card) => (
                    <Blueprint
                        key={card.label}
                        className="flex items-center gap-3 p-4"
                    >
                        <card.icon
                            size={22}
                            strokeWidth={1.4}
                            className="text-(--brand-700)"
                        />
                        <span>
                            <strong className="block font-mono text-2xl font-semibold">
                                {card.value}
                            </strong>
                            <span className="text-xs text-(--color-neutral-600)">
                                {card.label}
                            </span>
                        </span>
                    </Blueprint>
                ))}
            </div>

            <div className="cms-two-col grid items-start gap-5 xl:grid-cols-[minmax(0,1.25fr)_minmax(320px,.75fr)]">
                <Blueprint className="overflow-hidden p-0">
                    <div className="border-b border-(--color-divider) px-4 py-3">
                        <h2 className="ui-card-title m-0">
                            Активные предупреждения
                        </h2>
                    </div>
                    {alerts.length === 0 ? (
                        <EmptyState
                            title="Активных предупреждений нет"
                            hint="Новые события появятся здесь автоматически."
                        />
                    ) : (
                        alerts.map((alert) => (
                            <div
                                key={alert.id}
                                className="flex flex-wrap items-center gap-3 border-b border-(--color-divider) px-4 py-3 last:border-0"
                            >
                                <SeverityBadge severity={alert.severity} />
                                <span className="min-w-0 flex-1">
                                    <strong className="block truncate text-sm font-medium">
                                        {alert.title}
                                    </strong>
                                    <span className="mt-1 block text-xs text-(--color-neutral-600)">
                                        {alert.regions ||
                                            'Территория не указана'}
                                        {alert.ends_at
                                            ? ` · до ${alert.ends_at}`
                                            : ''}
                                    </span>
                                </span>
                                <LinkButton
                                    href={alert.url}
                                    size="sm"
                                    variant="ghost"
                                >
                                    Открыть
                                </LinkButton>
                            </div>
                        ))
                    )}
                </Blueprint>

                <Blueprint className="overflow-hidden p-0">
                    <div className="border-b border-(--color-divider) px-4 py-3">
                        <h2 className="ui-card-title m-0">Карта регионов</h2>
                    </div>
                    {regions.map((region) => (
                        <div
                            key={region.key}
                            className="flex items-center gap-3 border-b border-(--color-divider) px-4 py-3 last:border-0"
                        >
                            <span className="min-w-0 flex-1">
                                <strong className="block text-sm font-medium">
                                    {region.name}
                                </strong>
                                <span className="text-xs text-(--color-neutral-600)">
                                    {region.statusText}
                                </span>
                            </span>
                            <Tag tone={levelTone[region.level] ?? 'neutral'}>
                                {region.count}
                            </Tag>
                        </div>
                    ))}
                </Blueprint>
            </div>

            <section aria-labelledby="operations-heading" className="mt-5">
                <div className="mb-4">
                    <h2
                        id="operations-heading"
                        className="m-0 text-xl font-semibold"
                    >
                        Надёжность API и очередей
                    </h2>
                    <span className="mt-1 block text-sm text-(--color-neutral-600)">
                        {sampleWindow(operations.api.window)} · последние 200
                        sampled/slow/error запросов и заданий без URL,
                        содержимого и персональных данных. Доля попаданий в кэш
                        считается по всем запросам за сутки, а не по этой
                        выборке — иначе медленные ответы перевесили бы
                    </span>
                </div>

                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    {[
                        {
                            label: 'API p95',
                            value:
                                operations.api.p95_ms === null
                                    ? '—'
                                    : `${operations.api.p95_ms} мс`,
                        },
                        {
                            label: 'Отказы сервера (5xx)',
                            value: operations.api.server_errors,
                        },
                        {
                            label: 'Ответы 4xx',
                            value: operations.api.client_errors,
                        },
                        {
                            label: 'Очередь p95',
                            value:
                                operations.queue.p95_ms === null
                                    ? '—'
                                    : `${operations.queue.p95_ms} мс`,
                        },
                        {
                            label: 'Ошибки очереди',
                            value: operations.queue.failures,
                        },
                        {
                            label: 'Попаданий в кэш за сутки',
                            value:
                                operations.cache.hit_rate === null
                                    ? '—'
                                    : `${Math.round(operations.cache.hit_rate * 100)}%`,
                        },
                    ].map((metric) => (
                        <Blueprint key={metric.label} className="p-4">
                            <strong className="block font-mono text-2xl font-semibold">
                                {metric.value}
                            </strong>
                            <span className="mt-1 block text-xs text-(--color-neutral-600)">
                                {metric.label}
                            </span>
                        </Blueprint>
                    ))}
                </div>

                {operations.api.routes.length > 0 && (
                    <Blueprint className="mt-4 overflow-x-auto p-0">
                        <table className="w-full min-w-[560px] border-collapse text-left text-sm">
                            <caption className="sr-only">
                                p95 и ошибки публичного API по именованным
                                маршрутам
                            </caption>
                            <thead>
                                <tr className="border-b border-(--color-divider) text-xs text-(--color-neutral-600)">
                                    <th scope="col" className="px-4 py-3">
                                        Маршрут
                                    </th>
                                    <th scope="col" className="px-4 py-3">
                                        p95
                                    </th>
                                    <th scope="col" className="px-4 py-3">
                                        5xx / 4xx
                                    </th>
                                    <th
                                        scope="col"
                                        className="px-4 py-3 text-right"
                                    >
                                        Выборка
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {operations.api.routes.map((route) => (
                                    <tr
                                        key={route.route}
                                        className="border-b border-(--color-divider) last:border-0"
                                    >
                                        <th
                                            scope="row"
                                            className="px-4 py-3 font-mono text-xs"
                                        >
                                            {route.route}
                                        </th>
                                        <td className="px-4 py-3 font-mono">
                                            {route.p95_ms === null
                                                ? '—'
                                                : `${route.p95_ms} мс`}
                                        </td>
                                        <td className="px-4 py-3 font-mono">
                                            {route.server_errors} /{' '}
                                            {route.client_errors}
                                        </td>
                                        <td className="px-4 py-3 text-right font-mono">
                                            {route.samples}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </Blueprint>
                )}
            </section>

            <section aria-labelledby="rum-heading" className="mt-5">
                <div className="mb-4 flex flex-wrap items-end justify-between gap-3">
                    <span>
                        <h2
                            id="rum-heading"
                            className="m-0 text-xl font-semibold"
                        >
                            Скорость сайта у посетителей
                        </h2>
                        <span className="mt-1 block text-sm text-(--color-neutral-600)">
                            Core Web Vitals, p75 за {webVitals.period_days} дней
                            · без IP, user-agent и идентификаторов сессии
                        </span>
                    </span>
                    <Tag
                        tone={
                            webVitals.total_samples > 0 ? 'accent' : 'neutral'
                        }
                    >
                        {webVitals.total_samples} измерений
                    </Tag>
                </div>

                <div className="grid gap-4 md:grid-cols-3">
                    {webVitals.metrics.map((metric) => (
                        <Blueprint
                            key={metric.metric}
                            className="flex items-start gap-3 p-4"
                        >
                            <Gauge
                                aria-hidden="true"
                                size={22}
                                strokeWidth={1.4}
                                className="mt-1 text-(--brand-700)"
                            />
                            <span className="min-w-0 flex-1">
                                <span className="flex flex-wrap items-center justify-between gap-2">
                                    <strong className="font-mono text-sm">
                                        {metric.metric} p75
                                    </strong>
                                    <Tag tone={ratingTone[metric.rating]}>
                                        {ratingLabel[metric.rating]}
                                    </Tag>
                                </span>
                                <strong className="mt-2 block font-mono text-2xl font-semibold">
                                    {formatVital(metric)}
                                </strong>
                                <span className="mt-1 block text-xs text-(--color-neutral-600)">
                                    {metric.samples} измерений
                                    {metric.provisional && metric.samples > 0
                                        ? ' · предварительно (нужно 75)'
                                        : ''}
                                </span>
                            </span>
                        </Blueprint>
                    ))}
                </div>

                <div className="mt-5 grid items-start gap-5 xl:grid-cols-[minmax(0,1.35fr)_minmax(300px,.65fr)]">
                    <Blueprint className="overflow-x-auto p-0">
                        <table className="w-full min-w-[620px] border-collapse text-left text-sm">
                            <caption className="sr-only">
                                Значения p75 Core Web Vitals по маршрутам
                            </caption>
                            <thead>
                                <tr className="border-b border-(--color-divider) text-xs text-(--color-neutral-600)">
                                    <th scope="col" className="px-4 py-3">
                                        Маршрут
                                    </th>
                                    {webVitals.metrics.map((metric) => (
                                        <th
                                            key={metric.metric}
                                            scope="col"
                                            className="px-3 py-3 font-mono"
                                        >
                                            {metric.metric}
                                        </th>
                                    ))}
                                    <th
                                        scope="col"
                                        className="px-4 py-3 text-right"
                                    >
                                        Измерения
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {webVitals.routes.length === 0 ? (
                                    <tr>
                                        <td
                                            colSpan={5}
                                            className="px-4 py-8 text-center text-(--color-neutral-600)"
                                        >
                                            Данные появятся после реальных
                                            посещений публичного сайта.
                                        </td>
                                    </tr>
                                ) : (
                                    webVitals.routes.map((route) => (
                                        <tr
                                            key={route.route}
                                            className="border-b border-(--color-divider) last:border-0"
                                        >
                                            <th
                                                scope="row"
                                                className="max-w-[300px] truncate px-4 py-3 font-mono text-xs font-medium"
                                            >
                                                {route.route}
                                            </th>
                                            {route.metrics.map((metric) => (
                                                <td
                                                    key={metric.metric}
                                                    className="px-3 py-3 font-mono"
                                                >
                                                    {formatVital(metric)}
                                                </td>
                                            ))}
                                            <td className="px-4 py-3 text-right font-mono">
                                                {route.samples}
                                            </td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </Blueprint>

                    <Blueprint className="overflow-hidden p-0">
                        <div className="flex items-center gap-2 border-b border-(--color-divider) px-4 py-3">
                            <MonitorSmartphone
                                aria-hidden="true"
                                size={18}
                                strokeWidth={1.4}
                                className="text-(--brand-700)"
                            />
                            <h3 className="ui-card-title m-0">
                                По устройствам
                            </h3>
                        </div>
                        {webVitals.devices.length === 0 ? (
                            <EmptyState
                                title="Пока нет выборки"
                                hint="RUM не влияет на работу сайта и заполнится автоматически."
                            />
                        ) : (
                            webVitals.devices.map((device) => (
                                <div
                                    key={device.device}
                                    className="border-b border-(--color-divider) px-4 py-3 last:border-0"
                                >
                                    <span className="flex items-center justify-between gap-3">
                                        <strong className="text-sm font-medium capitalize">
                                            {device.device}
                                        </strong>
                                        <span className="font-mono text-xs text-(--color-neutral-600)">
                                            {device.samples}
                                        </span>
                                    </span>
                                    <span className="mt-2 flex flex-wrap gap-2">
                                        {device.metrics.map((metric) => (
                                            <Tag
                                                key={metric.metric}
                                                tone={ratingTone[metric.rating]}
                                            >
                                                {metric.metric}:{' '}
                                                {formatVital(metric)}
                                            </Tag>
                                        ))}
                                    </span>
                                </div>
                            ))
                        )}
                    </Blueprint>
                </div>
            </section>
        </>
    );
}
