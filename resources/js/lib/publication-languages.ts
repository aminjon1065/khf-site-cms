import type { PublicationCheck } from '@/cms/EditorialPreview';
import type { ContentLocale } from '@/lib/domain';

/**
 * A material may be published in a single language: the public site lists it
 * only on the language versions that have its title and text (documents need
 * only a title) and never substitutes another language. Missing translations
 * are therefore reported, not required — the same rule the server enforces in
 * PublicLocale and PublicationChecklist.
 */

export type TitleWord = 'заголовка' | 'названия';

/** How a notice names a missing text: an instruction's text is its steps or body. */
export type TextWord = 'текста' | 'ни шагов, ни текста';

/**
 * One language version as the site judges it. `hasText` is left out for
 * materials the site shows by title alone (documents).
 */
export interface LanguageVersion {
    title: string;
    hasText?: boolean;
}

/** What keeps a language version off the site. */
export type MissingPart = 'title' | 'text';

const LOCALES: ContentLocale[] = ['tg', 'ru', 'en'];

/**
 * English is optional (REQUIRED_LOCALES in lib/domain). Kept local because
 * this module is unit-tested under plain Node, without the `@/` alias.
 */
const REQUIRED: ContentLocale[] = ['tg', 'ru'];
const isRequiredLocale = (locale: ContentLocale): boolean =>
    REQUIRED.includes(locale);

/** Order in which a filled language is picked when the preferred one is empty. */
const FIRST_FILLED_ORDER: ContentLocale[] = ['ru', 'tg', 'en'];

const VERSIONS: Record<
    ContentLocale,
    { label: string; site: string; short: string }
> = {
    tg: { label: 'Таджикская', site: 'таджикской', short: 'ТҶ' },
    ru: { label: 'Русская', site: 'русской', short: 'РУ' },
    en: { label: 'Английская', site: 'английской', short: 'EN' },
};

export function hasAnyTranslation(
    values: Record<ContentLocale, string>,
): boolean {
    return LOCALES.some((locale) => values[locale].trim() !== '');
}

/**
 * Whether rich-text HTML has anything a reader sees. The editor keeps
 * `<p></p>` for a cleared field, and the server drops such a text on save
 * (RichText::sanitizeTranslations).
 */
export function hasRichText(html: string): boolean {
    if (/<(img|iframe)\b/i.test(html)) {
        return true;
    }

    return (
        html
            .replace(/<[^>]*>/g, '')
            .replace(/&nbsp;|&#160;/g, ' ')
            .trim() !== ''
    );
}

export function missingPart(version: LanguageVersion): MissingPart | null {
    if (version.title.trim() === '') {
        return 'title';
    }

    return version.hasText === false ? 'text' : null;
}

export function missingVersionNotice(
    locale: ContentLocale,
    titleWord: TitleWord = 'заголовка',
    missing: MissingPart = 'title',
    textWord: TextWord = 'текста',
): string {
    const what = missing === 'title' ? titleWord : textWord;

    return `Для ${VERSIONS[locale].short} нет ${what} — на ${VERSIONS[locale].site} версии сайта материал не появится.`;
}

/**
 * The language to preview first: the one being edited if it has a title,
 * otherwise the first language that appears on the site, otherwise the first
 * one with a title.
 */
export function previewLocale(
    versions: Record<ContentLocale, LanguageVersion>,
    preferred: ContentLocale,
): ContentLocale {
    const hasTitle = (locale: ContentLocale) =>
        versions[locale].title.trim() !== '';

    if (hasTitle(preferred)) {
        return preferred;
    }

    return (
        FIRST_FILLED_ORDER.find(
            (locale) => missingPart(versions[locale]) === null,
        ) ??
        FIRST_FILLED_ORDER.find(hasTitle) ??
        preferred
    );
}

export function languageChecks(
    completeness: Record<ContentLocale, number>,
    versions: Record<ContentLocale, LanguageVersion>,
    titleWord: TitleWord = 'заголовка',
    textWord: TextWord = 'текста',
): PublicationCheck[] {
    const anyComplete = LOCALES.some((locale) => completeness[locale] === 100);

    return [
        {
            label: 'Хотя бы одна языковая версия заполнена',
            ok: anyComplete,
            blocking: true,
            detail: anyComplete
                ? null
                : 'Заполните все поля материала хотя бы на одном языке.',
        },
        // English is optional: an untouched English version isn't listed.
        ...LOCALES.filter(
            (locale) =>
                isRequiredLocale(locale) ||
                versions[locale].title.trim() !== '',
        ).map((locale): PublicationCheck => {
            const percent = completeness[locale];
            const missing = missingPart(versions[locale]);

            return {
                label: isRequiredLocale(locale)
                    ? `${VERSIONS[locale].label} версия заполнена`
                    : `${VERSIONS[locale].label} версия заполнена (необязательно)`,
                ok: percent === 100,
                detail:
                    percent === 100
                        ? null
                        : missing !== null
                          ? `Нет ${missing === 'title' ? titleWord : textWord} — на ${VERSIONS[locale].site} версии сайта материал не появится.`
                          : `Заполнена на ${percent}%.`,
            };
        }),
    ];
}
