import { Inbox } from 'lucide-react';
import type { CSSProperties, ReactNode } from 'react';
import { cn } from '@/lib/utils';
import { Blueprint } from './Blueprint';

export function EmptyState({
    icon,
    title,
    hint,
    action,
    className,
}: {
    icon?: ReactNode;
    title: ReactNode;
    hint?: ReactNode;
    action?: ReactNode;
    className?: string;
}) {
    return (
        <div className={cn('ui-empty', className)}>
            <span className="ui-empty-icon">
                {icon ?? <Inbox size={30} strokeWidth={1.5} />}
            </span>
            <div
                style={{
                    fontFamily: 'var(--font-heading)',
                    fontWeight: 600,
                    fontSize: 16,
                }}
            >
                {title}
            </div>
            {hint && <div style={{ fontSize: 13, maxWidth: 380 }}>{hint}</div>}
            {action}
        </div>
    );
}

export function Skeleton({
    w,
    h = 14,
    className,
    style,
}: {
    w?: number | string;
    h?: number | string;
    className?: string;
    style?: CSSProperties;
}) {
    return (
        <span
            className={cn('ui-skel', className)}
            style={{
                display: 'block',
                width: w ?? '100%',
                height: h,
                ...style,
            }}
        />
    );
}

export function Avatar({
    initials,
    size = 30,
    className,
}: {
    initials: string;
    size?: number;
    className?: string;
}) {
    return (
        <span
            className={cn('ui-avatar', className)}
            style={{
                width: size,
                height: size,
                fontSize: Math.round(size * 0.4),
            }}
        >
            {initials}
        </span>
    );
}

export function MetricCard({
    value,
    label,
    tone,
}: {
    value: ReactNode;
    label: ReactNode;
    tone?: 'warn' | 'danger' | 'ok' | 'accent';
}) {
    const color =
        tone === 'warn'
            ? 'var(--warn)'
            : tone === 'danger'
              ? 'var(--danger)'
              : tone === 'ok'
                ? 'var(--ok)'
                : tone === 'accent'
                  ? 'var(--color-accent-700)'
                  : 'var(--color-text)';

    return (
        <Blueprint className="ui-metric">
            <span className="val" style={{ color }}>
                {value}
            </span>
            <span className="lbl">{label}</span>
        </Blueprint>
    );
}

export interface TimelineStep {
    title: ReactNode;
    meta?: ReactNode;
    state: 'done' | 'active' | 'todo' | 'warn';
}

export function WorkflowTimeline({ steps }: { steps: TimelineStep[] }) {
    return (
        <div className="ui-timeline">
            {steps.map((s, i) => (
                <div key={i} className="ui-timeline-item">
                    <span
                        className={cn(
                            'ui-timeline-dot',
                            s.state === 'done' && 'is-done',
                            s.state === 'warn' && 'is-danger',
                        )}
                        style={
                            s.state === 'active'
                                ? {
                                      borderColor: 'var(--color-accent)',
                                      boxShadow:
                                          '0 0 0 3px var(--color-accent-100)',
                                  }
                                : undefined
                        }
                    />
                    <div style={{ flex: 1 }}>
                        <div
                            style={{
                                fontSize: 13.5,
                                fontWeight: s.state === 'active' ? 600 : 500,
                                color:
                                    s.state === 'active'
                                        ? 'var(--color-accent-800)'
                                        : 'var(--color-text)',
                            }}
                        >
                            {s.title}
                        </div>
                        {s.meta && (
                            <div
                                style={{
                                    fontSize: 12,
                                    color: 'var(--color-neutral-600)',
                                }}
                            >
                                {s.meta}
                            </div>
                        )}
                    </div>
                </div>
            ))}
        </div>
    );
}

export interface ActivityEntry {
    initials: string;
    text: ReactNode;
    meta: ReactNode;
}

export function ActivityFeed({ items }: { items: ActivityEntry[] }) {
    return (
        <div>
            {items.map((a, i) => (
                <div
                    key={i}
                    className="ui-activity-item"
                    style={
                        i === items.length - 1 ? { borderBottom: 0 } : undefined
                    }
                >
                    <Avatar initials={a.initials} size={30} />
                    <div style={{ flex: 1 }}>
                        <div>{a.text}</div>
                        <div
                            style={{
                                fontSize: 11.5,
                                color: 'var(--color-neutral-500)',
                                marginTop: 2,
                            }}
                        >
                            {a.meta}
                        </div>
                    </div>
                </div>
            ))}
        </div>
    );
}
