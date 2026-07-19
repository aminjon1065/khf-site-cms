import { TriangleAlert } from 'lucide-react';
import type { ReactNode } from 'react';
import {
    localeShort,
    LOCALES,
    severityBadgeClass,
    statusTone,
    toneColor,
} from '@/lib/domain';
import type { ContentLocale, ContentStatus, Severity } from '@/lib/domain';
import { useT } from '@/lib/i18n';
import { cn } from '@/lib/utils';

type TagTone =
    'accent' | 'neutral' | 'outline' | 'ok' | 'warn' | 'danger' | 'info';

export function Tag({
    tone = 'neutral',
    className,
    children,
}: {
    tone?: TagTone;
    className?: string;
    children: ReactNode;
}) {
    return (
        <span className={cn('ui-tag', `ui-tag-${tone}`, className)}>
            {children}
        </span>
    );
}

/** Uppercase severity pill (5 levels). */
export function SeverityBadge({
    severity,
    className,
}: {
    severity: Severity;
    className?: string;
}) {
    const { t } = useT();

    return (
        <span className={cn('ui-sev', severityBadgeClass[severity], className)}>
            <span className="ui-sev-mark" aria-hidden />
            {t(`severity.${severity}`)}
        </span>
    );
}

/** Dot + text status indicator (never colour alone). */
export function StatusBadge({
    status,
    className,
}: {
    status: ContentStatus;
    className?: string;
}) {
    const { t } = useT();
    const tone = statusTone[status] ?? 'neutral';

    return (
        <span className={cn('ui-status', className)}>
            <span
                className="ui-status-dot"
                style={{ background: toneColor[tone] }}
                aria-hidden
            />
            {t(`status.${status}`)}
        </span>
    );
}

/**
 * "ТҶ 100 · РУ 100 · EN 40" language completeness row with a warning icon
 * when any tracked locale is below 100%.
 */
export function LanguageBadges({
    completeness,
    locales = LOCALES,
    showWarning = true,
}: {
    completeness: Partial<Record<ContentLocale, number>>;
    locales?: readonly ContentLocale[];
    showWarning?: boolean;
}) {
    const incomplete = locales.some((l) => (completeness[l] ?? 0) < 100);

    return (
        <span className="ui-status" style={{ gap: 8, fontSize: 12.5 }}>
            {locales.map((l, i) => {
                const pct = completeness[l] ?? 0;

                return (
                    <span key={l} className="ui-mono">
                        {i > 0 && (
                            <span style={{ color: 'var(--color-neutral-400)' }}>
                                {' '}
                                ·{' '}
                            </span>
                        )}
                        <span
                            style={{
                                color:
                                    pct === 0
                                        ? 'var(--danger)'
                                        : pct < 100
                                          ? 'var(--warn)'
                                          : 'var(--color-neutral-700)',
                            }}
                        >
                            {localeShort[l]} {pct}
                        </span>
                    </span>
                );
            })}
            {showWarning && incomplete && (
                <TriangleAlert
                    size={14}
                    strokeWidth={1.5}
                    style={{ color: 'var(--warn)' }}
                    aria-label="Перевод не завершён"
                />
            )}
        </span>
    );
}

/** Slim completeness progress bar. */
export function TranslationProgress({
    value,
    className,
}: {
    value: number;
    className?: string;
}) {
    return (
        <span
            className={cn('ui-progress', value >= 100 && 'full', className)}
            style={{ display: 'block' }}
        >
            <span style={{ width: `${Math.min(100, Math.max(0, value))}%` }} />
        </span>
    );
}
