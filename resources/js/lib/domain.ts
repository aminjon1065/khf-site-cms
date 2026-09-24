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

/**
 * Statuses whose material is on the site or will get there without another
 * decision: someone without the publish permission changes such a material
 * only through approval (PendingChangeService::LIVE_STATUSES).
 */
export const LIVE_STATUSES: readonly ContentStatus[] = [
    'published',
    'updated',
    'completed',
    'scheduled',
];

/**
 * Why photos and files of a live material can't be changed in its editor:
 * the edit goes to approval, and the server turns media changes away
 * (PendingChangeService::assertNoMediaChanges).
 */
export const MEDIA_LOCKED_NOTE =
    'Фото и файлы опубликованного материала меняет сотрудник с правом публикации.';

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
    accent: 'var(--brand-600)',
    danger: 'var(--danger)',
};

export const LOCALES = ['tg', 'ru', 'en'] as const;
export type ContentLocale = (typeof LOCALES)[number];

/**
 * Languages every material is expected in. English is optional (owner
 * decision, 2026-09-23): an empty English version is not a warning. Mirrors
 * App\Support\ContentLocales::REQUIRED.
 */
export const REQUIRED_LOCALES: readonly ContentLocale[] = ['tg', 'ru'];

export function isRequiredLocale(locale: ContentLocale): boolean {
    return REQUIRED_LOCALES.includes(locale);
}

export const localeShort: Record<ContentLocale, string> = {
    tg: 'ТҶ',
    ru: 'РУ',
    en: 'EN',
};
