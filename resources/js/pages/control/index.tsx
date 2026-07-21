import { Head } from '@inertiajs/react';
import { Clock3, MapPinned, Radio, Siren } from 'lucide-react';
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
}

const levelTone: Record<string, 'neutral' | 'accent' | 'warn' | 'danger'> = {
    none: 'neutral',
    info: 'accent',
    warning: 'warn',
    danger: 'danger',
    critical: 'danger',
};

export default function ControlCenter({
    state,
    regions,
    metrics,
    alerts,
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
                            className="text-(--color-accent-700)"
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
        </>
    );
}
