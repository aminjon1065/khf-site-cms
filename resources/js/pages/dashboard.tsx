import { Head, Link } from '@inertiajs/react';
import { ArrowRight, CheckCircle2, TriangleAlert } from 'lucide-react';
import { useAuth } from '@/lib/auth';
import { toneColor } from '@/lib/domain';
import type { Severity } from '@/lib/domain';
import { useT } from '@/lib/i18n';
import { SeverityBadge, Tag } from '@/ui/Badge';
import { Blueprint } from '@/ui/Blueprint';
import { LinkButton } from '@/ui/Button';
import { ActivityFeed, MetricCard } from '@/ui/Feedback';
import { PageHeader } from '@/ui/PageHeader';

interface AlertLite {
    id: number;
    title: string;
    hazard_label: string;
    severity: Severity;
    regions: { name: string }[];
    ends_at: string | null;
    author: string | null;
    summary: string | null;
}
interface Metric {
    key: string;
    value: number;
    label: string;
    tone: 'warn' | 'danger' | 'ok' | 'accent' | null;
}
interface Task {
    kind: string;
    kind_label: string;
    title: string;
    meta: string;
    due: string;
    due_tone: string;
    action: string;
    href: string;
}
interface ActivityItem {
    initials: string;
    text: string;
    when: string;
    section: string;
}
interface RegionStatus {
    id: number;
    name: string;
    status: string;
}
interface CalendarEvent {
    date: string;
    time: string;
    label: string;
    tone: string;
}

interface Props {
    metrics: Metric[];
    operationalLevel: 'calm' | 'active' | 'critical';
    activeAlerts: AlertLite[];
    regionStatuses: RegionStatus[];
    tasks: Task[];
    activity: ActivityItem[];
    calendar: CalendarEvent[];
    today: string;
    greetingName: string;
}

const regionStateLabel: Record<string, { label: string; tone: string }> = {
    normal: { label: 'штатно', tone: 'ok' },
    attention: { label: 'информация', tone: 'accent' },
    warning: { label: 'предупреждение', tone: 'warn' },
};

const kindTone: Record<string, string> = {
    urgent: 'danger',
    expiring: 'warn',
    translation: 'warn',
    publication: 'info',
};

export default function Dashboard({
    metrics,
    operationalLevel,
    activeAlerts,
    regionStatuses,
    tasks,
    activity,
    calendar,
    today,
    greetingName,
}: Props) {
    const { t } = useT();
    const user = useAuth();

    return (
        <>
            <Head title={t('nav.dashboard')} />
            <PageHeader
                eyebrow={`${user?.role_label ?? ''} · ${today}`}
                title={`Добро пожаловать, ${greetingName}`}
                subtitle={
                    tasks.length > 0
                        ? `${tasks.length} материала требуют вашего внимания. Действуют ${metrics[0]?.value ?? 0} предупреждения.`
                        : 'Обстановка штатная. Все материалы в работе.'
                }
            />

            {/* Operational status */}
            <div
                className={`ui-opstatus ${operationalLevel !== 'calm' ? (operationalLevel === 'critical' ? 'is-critical' : 'is-active') : ''}`}
                style={{ marginBottom: 20, padding: 0 }}
            >
                <div
                    style={{
                        display: 'flex',
                        alignItems: 'center',
                        gap: 12,
                        padding: '12px 16px',
                        background:
                            operationalLevel === 'calm'
                                ? 'var(--ok-soft)'
                                : operationalLevel === 'critical'
                                  ? 'var(--danger-soft)'
                                  : 'var(--sev-warning-soft)',
                        borderBottom: '1px solid var(--color-divider)',
                    }}
                >
                    {operationalLevel === 'calm' ? (
                        <CheckCircle2
                            size={20}
                            strokeWidth={1.5}
                            style={{ color: 'var(--ok)' }}
                        />
                    ) : (
                        <TriangleAlert
                            size={20}
                            strokeWidth={1.5}
                            style={{
                                color:
                                    operationalLevel === 'critical'
                                        ? 'var(--danger)'
                                        : 'var(--sev-warning)',
                            }}
                        />
                    )}
                    <strong
                        style={{
                            fontFamily: 'var(--font-heading)',
                            fontSize: 14,
                            letterSpacing: '0.03em',
                        }}
                    >
                        {operationalLevel === 'calm'
                            ? 'ОПЕРАТИВНЫЙ СТАТУС — АКТИВНЫХ ПРЕДУПРЕЖДЕНИЙ НЕТ'
                            : `ОПЕРАТИВНЫЙ СТАТУС — ${operationalLevel === 'critical' ? 'КРАСНЫЙ' : 'ОРАНЖЕВЫЙ'} УРОВЕНЬ`}
                    </strong>
                    <span
                        style={{
                            fontSize: 12.5,
                            color: 'var(--color-neutral-600)',
                        }}
                    >
                        {activeAlerts.length} активных · обновлено{' '}
                        {today.split(',').pop()?.trim()}
                    </span>
                    <LinkButton
                        href="/alerts"
                        variant="secondary"
                        size="sm"
                        style={{ marginLeft: 'auto' }}
                    >
                        Все предупреждения
                    </LinkButton>
                </div>

                <div
                    style={{
                        display: 'grid',
                        gridTemplateColumns:
                            'minmax(0,1fr) minmax(0,1fr) 280px',
                        gap: 16,
                        padding: 16,
                        background: '#fbfbfc',
                    }}
                    className="cms-ops-grid"
                >
                    {activeAlerts.slice(0, 2).map((a) => (
                        <div key={a.id} style={{ minWidth: 0 }}>
                            <SeverityBadge severity={a.severity} />
                            <div
                                style={{
                                    fontSize: 11.5,
                                    color: 'var(--color-neutral-500)',
                                    margin: '7px 0 2px',
                                }}
                            >
                                {a.hazard_label} ·{' '}
                                {a.regions.map((r) => r.name).join(', ')}
                            </div>
                            <Link
                                href={`/alerts/${a.id}/edit`}
                                style={{
                                    fontFamily: 'var(--font-heading)',
                                    fontWeight: 600,
                                    fontSize: 15,
                                    color: 'var(--color-text)',
                                    textDecoration: 'none',
                                    display: 'block',
                                }}
                            >
                                {a.title}
                            </Link>
                            <div
                                style={{
                                    fontSize: 12,
                                    color: 'var(--color-neutral-600)',
                                    marginTop: 4,
                                }}
                            >
                                {a.ends_at
                                    ? `Действует до ${new Date(a.ends_at).toLocaleString('ru-RU', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' })}`
                                    : 'Бессрочно'}
                                {a.author
                                    ? ` · Ответственный: ${a.author}`
                                    : ''}
                            </div>
                        </div>
                    ))}
                    {activeAlerts.length === 0 && (
                        <div
                            style={{
                                gridColumn: 'span 2',
                                fontSize: 13,
                                color: 'var(--color-neutral-600)',
                            }}
                        >
                            Обстановка по всем регионам штатная.
                        </div>
                    )}
                    <div>
                        <div className="ui-kicker" style={{ marginBottom: 8 }}>
                            Обстановка по регионам
                        </div>
                        {regionStatuses.map((r) => {
                            const st =
                                regionStateLabel[r.status] ??
                                regionStateLabel.normal;

                            return (
                                <div
                                    key={r.id}
                                    style={{
                                        display: 'flex',
                                        alignItems: 'center',
                                        gap: 8,
                                        fontSize: 13,
                                        padding: '3px 0',
                                    }}
                                >
                                    <span
                                        style={{
                                            width: 8,
                                            height: 8,
                                            borderRadius: '50%',
                                            background:
                                                toneColor[st.tone as 'ok'],
                                        }}
                                    />
                                    <span style={{ flex: 1 }}>{r.name}</span>
                                    <span
                                        style={{
                                            color: 'var(--color-neutral-600)',
                                            fontSize: 12,
                                        }}
                                    >
                                        {st.label}
                                    </span>
                                </div>
                            );
                        })}
                    </div>
                </div>
            </div>

            {/* Metrics */}
            <div
                style={{
                    display: 'grid',
                    gridTemplateColumns: 'repeat(6, 1fr)',
                    gap: 12,
                    marginBottom: 20,
                }}
                className="cms-metrics-grid"
            >
                {metrics.map((m) => (
                    <MetricCard
                        key={m.key}
                        value={m.value}
                        label={m.label}
                        tone={m.tone ?? undefined}
                    />
                ))}
            </div>

            {/* Two columns */}
            <div
                style={{
                    display: 'grid',
                    gridTemplateColumns: 'minmax(0, 1.5fr) minmax(0, 1fr)',
                    gap: 20,
                }}
                className="cms-dash-grid"
            >
                <div
                    style={{
                        display: 'flex',
                        flexDirection: 'column',
                        gap: 20,
                    }}
                >
                    <section>
                        <SectionTitle
                            title={`Требует внимания · ${tasks.length}`}
                            link="/approvals"
                            linkLabel="Центр согласования"
                        />
                        <Blueprint>
                            {tasks.length === 0 ? (
                                <div
                                    style={{
                                        padding: 24,
                                        textAlign: 'center',
                                        color: 'var(--color-neutral-600)',
                                        fontSize: 13,
                                    }}
                                >
                                    Нет задач, требующих внимания.
                                </div>
                            ) : (
                                tasks.map((task, i) => (
                                    <div
                                        key={i}
                                        style={{
                                            display: 'flex',
                                            alignItems: 'center',
                                            gap: 12,
                                            padding: '12px 14px',
                                            borderBottom:
                                                i < tasks.length - 1
                                                    ? '1px solid var(--color-divider)'
                                                    : 0,
                                        }}
                                    >
                                        <span
                                            style={{ width: 92, flex: 'none' }}
                                        >
                                            <Tag
                                                tone={
                                                    (kindTone[task.kind] ??
                                                        'neutral') as 'danger'
                                                }
                                            >
                                                {task.kind_label}
                                            </Tag>
                                        </span>
                                        <div style={{ flex: 1, minWidth: 0 }}>
                                            <div
                                                style={{
                                                    fontSize: 13.5,
                                                    fontWeight: 500,
                                                    whiteSpace: 'nowrap',
                                                    overflow: 'hidden',
                                                    textOverflow: 'ellipsis',
                                                }}
                                            >
                                                {task.title}
                                            </div>
                                            <div
                                                style={{
                                                    fontSize: 12,
                                                    color: 'var(--color-neutral-600)',
                                                }}
                                            >
                                                {task.meta}
                                            </div>
                                        </div>
                                        <span
                                            style={{
                                                fontSize: 12.5,
                                                color:
                                                    toneColor[
                                                        task.due_tone as 'warn'
                                                    ] ??
                                                    'var(--color-neutral-600)',
                                                whiteSpace: 'nowrap',
                                            }}
                                        >
                                            {task.due}
                                        </span>
                                        <LinkButton
                                            href={task.href}
                                            variant="secondary"
                                            size="sm"
                                        >
                                            {task.action}
                                        </LinkButton>
                                    </div>
                                ))
                            )}
                        </Blueprint>
                    </section>

                    <section>
                        <SectionTitle
                            title="Последняя активность"
                            link="/activity"
                            linkLabel="Журнал действий"
                        />
                        <Blueprint style={{ padding: '4px 14px' }}>
                            {activity.length === 0 ? (
                                <div
                                    style={{
                                        padding: 20,
                                        textAlign: 'center',
                                        color: 'var(--color-neutral-600)',
                                        fontSize: 13,
                                    }}
                                >
                                    Активности пока нет.
                                </div>
                            ) : (
                                <ActivityFeed
                                    items={activity.map((a) => ({
                                        initials: a.initials,
                                        text: a.text,
                                        meta: `${a.when} · ${a.section}`,
                                    }))}
                                />
                            )}
                        </Blueprint>
                    </section>
                </div>

                <section>
                    <SectionTitle title="Календарь публикаций" />
                    <Blueprint style={{ padding: 14 }}>
                        {calendar.length === 0 ? (
                            <div
                                style={{
                                    padding: 16,
                                    textAlign: 'center',
                                    color: 'var(--color-neutral-600)',
                                    fontSize: 13,
                                }}
                            >
                                Событий на неделю нет.
                            </div>
                        ) : (
                            calendar.map((ev, i) => (
                                <div
                                    key={i}
                                    style={{
                                        display: 'flex',
                                        gap: 10,
                                        padding: '8px 0',
                                        borderBottom:
                                            i < calendar.length - 1
                                                ? '1px solid var(--color-divider)'
                                                : 0,
                                    }}
                                >
                                    <span
                                        style={{
                                            fontSize: 12,
                                            color: 'var(--color-accent-700)',
                                            width: 52,
                                            flex: 'none',
                                        }}
                                    >
                                        {ev.date
                                            ? new Date(
                                                  ev.date,
                                              ).toLocaleDateString('ru-RU', {
                                                  day: '2-digit',
                                                  weekday: 'short',
                                              })
                                            : '—'}
                                    </span>
                                    <span
                                        style={{
                                            width: 3,
                                            background:
                                                toneColor[ev.tone as 'ok'] ??
                                                'var(--color-accent-500)',
                                            flex: 'none',
                                        }}
                                    />
                                    <div style={{ flex: 1 }}>
                                        <div style={{ fontSize: 13 }}>
                                            {ev.label}
                                        </div>
                                        <div
                                            style={{
                                                fontSize: 11.5,
                                                color: 'var(--color-neutral-500)',
                                            }}
                                        >
                                            {ev.time}
                                        </div>
                                    </div>
                                </div>
                            ))
                        )}
                    </Blueprint>
                </section>
            </div>
        </>
    );
}

function SectionTitle({
    title,
    link,
    linkLabel,
}: {
    title: string;
    link?: string;
    linkLabel?: string;
}) {
    return (
        <div
            style={{
                display: 'flex',
                alignItems: 'baseline',
                justifyContent: 'space-between',
                marginBottom: 8,
            }}
        >
            <h2
                style={{
                    fontFamily: 'var(--font-heading)',
                    fontWeight: 600,
                    fontSize: 16,
                }}
            >
                {title}
            </h2>
            {link && (
                <Link
                    href={link}
                    style={{
                        fontSize: 13,
                        display: 'inline-flex',
                        alignItems: 'center',
                        gap: 4,
                    }}
                >
                    {linkLabel} <ArrowRight size={13} strokeWidth={1.5} />
                </Link>
            )}
        </div>
    );
}
