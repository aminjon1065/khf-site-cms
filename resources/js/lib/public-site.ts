import { usePage } from '@inertiajs/react';
import type { ContentLocale } from '@/lib/domain';
import type { SharedProps } from '@/types/cms';

/**
 * Addresses on the public site (khf-site-front). Mirrors App\Support\PublicSite:
 * the base URL comes from the server (FRONTEND_URL), so links from a staging
 * CMS open the staging site; content locale `tg` is served under `/tj`.
 */

export type PublicContentType =
    'news' | 'instruction' | 'announcement' | 'project' | 'alert' | 'document';

const DETAIL_PATHS: Partial<Record<PublicContentType, string>> = {
    news: '/news',
    instruction: '/guides',
    announcement: '/announcements',
    project: '/projects',
    alert: '/alerts',
};

const SECTION_PATHS: Record<PublicContentType, string> = {
    news: '/news',
    instruction: '/guides',
    announcement: '/announcements',
    project: '/projects',
    alert: '/alerts',
    document: '/documents',
};

export function localeSegment(locale: ContentLocale): string {
    return locale === 'tg' ? 'tj' : locale;
}

export function usePublicSiteUrl(): string {
    const { public_site_url } = usePage<SharedProps>().props;

    return (public_site_url || 'https://khf.tj').replace(/\/+$/, '');
}

/** Locale-less path of a record's own page; null when the type has none. */
export function publicPath(
    type: PublicContentType,
    slug: string | null | undefined,
): string | null {
    const prefix = DETAIL_PATHS[type];

    return prefix && slug ? `${prefix}/${slug}` : null;
}

export function publicSectionPath(type: PublicContentType): string {
    return SECTION_PATHS[type];
}

export function siteUrl(
    base: string,
    path: string,
    locale: ContentLocale = 'ru',
): string {
    const normalized = path === '/' ? '' : `/${path.replace(/^\/+/, '')}`;

    return `${base}/${localeSegment(locale)}${normalized}`;
}

/** An URL as people read it: without the scheme (khf.tj/ru/news/…). */
export function displayUrl(url: string): string {
    return url.replace(/^https?:\/\//, '');
}
