import type { PublicationCheck } from '@/cms/EditorialPreview';
import type { ContentLocale } from '@/lib/domain';

/**
 * A material may be published in a single language: the public site lists it
 * only on the language versions where its title exists and never substitutes
 * another language. Missing translations are therefore reported, not required
 * — the same rule the server enforces in PublicationChecklist.
 */

export type TitleWord = 'заголовка' | 'названия';

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

export function missingVersionNotice(
    locale: ContentLocale,
    titleWord: TitleWord = 'заголовка',
): string {
    return `Для ${VERSIONS[locale].short} нет ${titleWord} — на ${VERSIONS[locale].site} версии сайта материал не появится.`;
}

/**
 * The language to preview first: the one being edited if it has a title,
 * otherwise the first language the material is filled in.
 */
export function previewLocale(
    versions: Record<ContentLocale, { title: string }>,
    preferred: ContentLocale,
): ContentLocale {
    const hasTitle = (locale: ContentLocale) =>
        versions[locale].title.trim() !== '';

    if (hasTitle(preferred)) {
        return preferred;
    }

    return FIRST_FILLED_ORDER.find(hasTitle) ?? preferred;
}

export function languageChecks(
    completeness: Record<ContentLocale, number>,
    titles: Record<ContentLocale, string>,
    titleWord: TitleWord = 'заголовка',
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
                isRequiredLocale(locale) || titles[locale].trim() !== '',
        ).map((locale): PublicationCheck => {
            const percent = completeness[locale];

            return {
                label: isRequiredLocale(locale)
                    ? `${VERSIONS[locale].label} версия заполнена`
                    : `${VERSIONS[locale].label} версия заполнена (необязательно)`,
                ok: percent === 100,
                detail:
                    percent === 100
                        ? null
                        : titles[locale].trim() === ''
                          ? `Нет ${titleWord} — на ${VERSIONS[locale].site} версии сайта материал не появится.`
                          : `Заполнена на ${percent}%.`,
            };
        }),
    ];
}
