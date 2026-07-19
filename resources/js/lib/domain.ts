/**
 * Frontend mirror of the PHP domain enums — value → presentation mappings.
 * Labels come from i18n (`severity.<value>`, `status.<value>`, ...).
 */

export type Severity = 'info' | 'attention' | 'warning' | 'danger' | 'critical';

export type ContentStatus =
    | 'draft'
    | 'review'
    | 'translation_check'
    | 'approved'
    | 'scheduled'
    | 'published'
    | 'updated'
    | 'completed'
    | 'cancelled'
    | 'returned'
    | 'archived';

export type StatusTone = 'neutral' | 'warn' | 'ok' | 'accent' | 'danger';

export const SEVERITY_ORDER: Severity[] = [
    'info',
    'attention',
    'warning',
    'danger',
    'critical',
];

export const severityBadgeClass: Record<Severity, string> = {
    info: 'ui-sev-info',
    attention: 'ui-sev-attention',
    warning: 'ui-sev-warning',
    danger: 'ui-sev-danger',
    critical: 'ui-sev-critical',
};

export const statusTone: Record<ContentStatus, StatusTone> = {
    draft: 'neutral',
    review: 'warn',
    translation_check: 'warn',
    approved: 'ok',
    scheduled: 'warn',
    published: 'ok',
    updated: 'accent',
    completed: 'neutral',
    cancelled: 'neutral',
    returned: 'danger',
    archived: 'neutral',
};

export const toneColor: Record<StatusTone, string> = {
    neutral: 'var(--color-neutral-500)',
    warn: 'var(--warn)',
    ok: 'var(--ok)',
    accent: 'var(--color-accent-600)',
    danger: 'var(--danger)',
};

export const LOCALES = ['tg', 'ru', 'en'] as const;
export type ContentLocale = (typeof LOCALES)[number];

export const localeShort: Record<ContentLocale, string> = {
    tg: 'ТҶ',
    ru: 'РУ',
    en: 'EN',
};
