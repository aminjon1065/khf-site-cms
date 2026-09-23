import { useForm } from '@inertiajs/react';
import { ExternalLink, Sliders, Sparkles, X } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { EditorialFormShell } from '@/cms/EditorialFormShell';
import { useCan } from '@/lib/auth';
import type { ContentLocale, ContentStatus } from '@/lib/domain';
import { displayUrl, siteUrl, usePublicSiteUrl } from '@/lib/public-site';
import { languageChecks } from '@/lib/publication-languages';
import { slugify } from '@/lib/slugify';
import { index, store, update } from '@/routes/pages';
import { Button } from '@/ui/Button';
import { Field, Input, Select, Textarea } from '@/ui/Field';
import { ReadinessWidget } from '@/ui/ReadinessWidget';
import { RichEditor } from '@/ui/RichEditor';

type LocaleMap = { ru: string; tg: string; en: string };
type PublishMode = 'now' | 'review';

interface Option {
    value: string;
    label: string;
}

interface PageData {
    id: number;
    title: LocaleMap;
    body: LocaleMap;
    seo_title: LocaleMap;
    seo_description: LocaleMap;
    slug: string;
    /** Where the page lives on the site: a section (`/leadership`) or `/pages/{slug}`. */
    public_path: string;
    /** The site depends on this page's slug: it can't be changed. */
    is_system: boolean;
    status: ContentStatus;
    parent_id: number | null;
    sort: number;
    updated_at: string;
    preview_url: string;
}

interface Props {
    page: PageData | null;
    reference: { parents: { value: number; label: string }[] };
}

const EMPTY: LocaleMap = { ru: '', tg: '', en: '' };

export default function PageForm({ page, reference }: Props) {
    const can = useCan();
    const isEdit = !!page;
    const [lang, setLang] = useState<ContentLocale>('ru');
    const [sidebarOpen, setSidebarOpen] = useState(true);
    const [sidebarTab, setSidebarTab] = useState<'document' | 'seo'>(
        'document',
    );
    const titleRef = useRef<HTMLTextAreaElement>(null);
    const publicSiteUrl = usePublicSiteUrl();
    const slugLocked = page?.is_system ?? false;
    // A new page's address follows its Russian title until the editor types
    // one; an existing page never changes address on its own (links to it
    // would break).
    const [slugTouched, setSlugTouched] = useState(isEdit);

    const form = useForm({
        title: { ...EMPTY, ...page?.title } as LocaleMap,
        body: { ...EMPTY, ...page?.body } as LocaleMap,
        seo_title: { ...EMPTY, ...page?.seo_title } as LocaleMap,
        seo_description: { ...EMPTY, ...page?.seo_description } as LocaleMap,
        slug: page?.slug ?? '',
        parent_id: (page?.parent_id ?? '') as number | '',
        sort: page?.sort ?? 0,
        publish_mode: 'review' as PublishMode,
        action: 'draft' as 'draft' | 'submit',
    });
    const { data, setData, processing, errors, isDirty } = form;

    const fieldError = (key: string): string | undefined =>
        (errors as Record<string, string | undefined>)[key];

    // Auto-resize title input
    useEffect(() => {
        if (titleRef.current) {
            titleRef.current.style.height = 'auto';
            titleRef.current.style.height = `${titleRef.current.scrollHeight}px`;
        }
    }, [data.title, lang]);

    // Completeness counts the required fields only (title + text), the same
    // as Page::languageCompleteness(): the search snippet is optional and is
    // built from the title and text when left empty.
    const completenessOf = (locale: ContentLocale): number =>
        (data.title[locale].trim() !== '' ? 50 : 0) +
        (data.body[locale].trim() !== '' ? 50 : 0);
    const compAll = {
        tg: completenessOf('tg'),
        ru: completenessOf('ru'),
        en: completenessOf('en'),
    };

    const setLocaleField = (
        field: 'title' | 'body' | 'seo_title' | 'seo_description',
        value: string,
    ) => setData(field, { ...data[field], [lang]: value });

    // 1-Click Copy across languages (WordPress Gutenberg UX)
    const handleCopyLocale = (from: ContentLocale, to: ContentLocale) => {
        setData((prev) => ({
            ...prev,
            title: { ...prev.title, [to]: prev.title[from] },
            body: { ...prev.body, [to]: prev.body[from] },
            seo_title: { ...prev.seo_title, [to]: prev.seo_title[from] },
            seo_description: {
                ...prev.seo_description,
                [to]: prev.seo_description[from],
            },
        }));
    };

    // Auto-slug generator
    const handleAutoSlug = () => {
        const source =
            data.title.ru?.trim() ||
            data.title.tg?.trim() ||
            data.title.en?.trim() ||
            '';

        if (source) {
            setData('slug', slugify(source));
        }
    };

    const parentOptions: Option[] = [
        { value: '', label: '— Верхний уровень (без родителя) —' },
        ...reference.parents.map((p) => ({
            value: String(p.value),
            label: p.label,
        })),
    ];

    // Readiness: what a page needs before it goes out. The address is
    // generated automatically and the search snippet is optional, so neither
    // is a readiness step.
    const hasTitle = data.title[lang]?.trim().length > 0;
    const hasBody = data.body[lang]?.trim().length > 30;
    const hasBilingual = Boolean(
        data.title.tg?.trim() && data.title.ru?.trim(),
    );
    const readinessScore =
        (hasTitle ? 40 : 0) + (hasBody ? 40 : 0) + (hasBilingual ? 20 : 0);
    const readinessItems = [
        {
            id: 'title',
            label: `Заголовок (${lang.toUpperCase()})`,
            done: hasTitle,
        },
        {
            id: 'body',
            label: 'Текст страницы',
            done: hasBody,
        },
        {
            id: 'bilingual',
            label: 'Двуязычие (ТҶ + РУ)',
            done: hasBilingual,
        },
    ];

    const publicPath = slugLocked
        ? page!.public_path
        : `/pages/${data.slug || '…'}`;
    const permalink = siteUrl(publicSiteUrl, publicPath, lang);

    const submit = (action: 'draft' | 'submit', mode?: PublishMode) => {
        form.transform((d) => ({
            ...d,
            action,
            publish_mode: mode ?? d.publish_mode,
            parent_id: d.parent_id === '' ? null : d.parent_id,
            ...(isEdit
                ? {
                      _method: 'put',
                      _editorial_version: page!.updated_at,
                  }
                : {}),
        }));

        form.post(isEdit ? update.url(page!.id) : store.url(), {
            preserveScroll: true,
        });
    };

    return (
        <EditorialFormShell
            variant="gutenberg"
            onCopyLocale={handleCopyLocale}
            title={isEdit ? 'Редактирование страницы' : 'Новая страница'}
            subtitle="Редакционная страница портала КЧС с чистым оформлением и SEO."
            backLabel="Страницы сайта"
            backHref={index.url()}
            status={page?.status}
            language={{
                active: lang,
                onChange: setLang,
                completeness: compAll,
            }}
            errors={errors}
            isDirty={isDirty}
            processing={processing}
            canPublish={can('pages.publish')}
            onSaveDraft={() => submit('draft')}
            onSubmitReview={() => submit('submit', 'review')}
            onPublishNow={() => submit('submit', 'now')}
            extraActions={
                <Button
                    variant={sidebarOpen ? 'primary' : 'secondary'}
                    icon={<Sliders size={15} />}
                    onClick={() => setSidebarOpen(!sidebarOpen)}
                    title={
                        sidebarOpen
                            ? 'Скрыть панель настроек'
                            : 'Показать панель настроек'
                    }
                >
                    <span className="wp-topbar-label">Настройки</span>
                </Button>
            }
            autosave={{
                contentType: 'pages',
                contentId: page?.id ?? null,
                baseVersion: page?.updated_at ?? null,
                data,
                onRecover: (recovered) =>
                    form.setData({ ...data, ...recovered }),
            }}
            preview={{
                locales: {
                    tg: {
                        title: data.title.tg,
                        body: data.body.tg,
                        seoTitle: data.seo_title.tg,
                        seoDescription: data.seo_description.tg,
                    },
                    ru: {
                        title: data.title.ru,
                        body: data.body.ru,
                        seoTitle: data.seo_title.ru,
                        seoDescription: data.seo_description.ru,
                    },
                    en: {
                        title: data.title.en,
                        body: data.body.en,
                        seoTitle: data.seo_title.en,
                        seoDescription: data.seo_description.en,
                    },
                },
                signedUrl: page?.preview_url,
                checklist: [
                    ...languageChecks(compAll, data.title),
                    {
                        label: 'Описание для поисковиков заполнено (необязательно)',
                        ok: (['tg', 'ru', 'en'] as ContentLocale[]).some(
                            (locale) =>
                                data.seo_title[locale].trim() !== '' &&
                                data.seo_description[locale].trim() !== '',
                        ),
                    },
                ],
            }}
        >
            <div
                className={`wp-editor-layout ${sidebarOpen ? 'has-sidebar' : 'no-sidebar'}`}
            >
                {/* ------------------------------------------- Document Canvas */}
                <main className="wp-editor-canvas-container" role="main">
                    <div className="wp-editor-canvas">
                        {/* Title field */}
                        <div className="wp-title-wrapper">
                            <textarea
                                ref={titleRef}
                                id={`page-title-${lang}`}
                                value={data.title[lang]}
                                onChange={(e) => {
                                    setLocaleField('title', e.target.value);

                                    if (!slugTouched && lang === 'ru') {
                                        setData((prev) => ({
                                            ...prev,
                                            slug: slugify(e.target.value),
                                        }));
                                    }
                                }}
                                placeholder={
                                    lang === 'ru'
                                        ? 'Заголовок страницы…'
                                        : lang === 'tg'
                                          ? 'Сарлавҳаи саҳифа…'
                                          : 'Page title…'
                                }
                                rows={1}
                                className="wp-title-input"
                                maxLength={255}
                            />
                            {(fieldError('title') ||
                                fieldError(`title.${lang}`)) && (
                                <div className="wp-field-error">
                                    {fieldError('title') ||
                                        fieldError(`title.${lang}`)}
                                </div>
                            )}
                        </div>

                        {/* Rich Editor Body */}
                        <div className="wp-body-wrapper">
                            <RichEditor
                                key={lang}
                                value={data.body[lang]}
                                onChange={(value) =>
                                    setLocaleField('body', value)
                                }
                                placeholder="Основной текст страницы…"
                            />
                            {(fieldError('body') ||
                                fieldError(`body.${lang}`)) && (
                                <div className="wp-field-error">
                                    {fieldError('body') ||
                                        fieldError(`body.${lang}`)}
                                </div>
                            )}
                        </div>
                    </div>
                </main>

                {/* ------------------------------------------- Inspector Sidebar */}
                {sidebarOpen && (
                    <aside
                        className="wp-inspector"
                        aria-label="Параметры страницы"
                    >
                        {/* Header */}
                        <div className="wp-inspector-header">
                            <span className="wp-inspector-title">
                                Инспектор страницы
                            </span>
                            <button
                                type="button"
                                className="wp-inspector-close"
                                onClick={() => setSidebarOpen(false)}
                                title="Закрыть панель"
                            >
                                <X size={16} />
                            </button>
                        </div>

                        {/* Tabs */}
                        <div className="wp-inspector-tabs" role="tablist">
                            <button
                                type="button"
                                role="tab"
                                aria-selected={sidebarTab === 'document'}
                                className={`wp-inspector-tab ${sidebarTab === 'document' ? 'is-active' : ''}`}
                                onClick={() => setSidebarTab('document')}
                            >
                                Свойства
                            </button>
                            <button
                                type="button"
                                role="tab"
                                aria-selected={sidebarTab === 'seo'}
                                className={`wp-inspector-tab ${sidebarTab === 'seo' ? 'is-active' : ''}`}
                                onClick={() => setSidebarTab('seo')}
                            >
                                SEO & Snippet
                            </button>
                        </div>

                        {/* Inspector Body */}
                        <div className="wp-inspector-body">
                            {sidebarTab === 'document' && (
                                <>
                                    {/* Traffic-light Readiness Score Widget */}
                                    <div className="wp-inspector-section">
                                        <ReadinessWidget
                                            title="Готовность страницы"
                                            score={readinessScore}
                                            items={readinessItems}
                                        />
                                    </div>

                                    {/* Permalink & Slug with Auto-generate */}
                                    <div className="wp-inspector-section">
                                        <div className="wp-inspector-section-title">
                                            Адрес страницы (URL)
                                        </div>
                                        <div className="wp-permalink-preview">
                                            <div className="wp-permalink-label">
                                                Ссылка на сайте:
                                            </div>
                                            <a
                                                href={permalink}
                                                target="_blank"
                                                rel="noreferrer"
                                                className="wp-permalink-link"
                                            >
                                                <span>
                                                    {displayUrl(permalink)}
                                                </span>
                                                <ExternalLink
                                                    size={12}
                                                    style={{ flex: 'none' }}
                                                />
                                            </a>
                                        </div>
                                        {slugLocked ? (
                                            <p
                                                style={{
                                                    margin: '8px 0 0',
                                                    fontSize: 12.5,
                                                    color: 'var(--color-neutral-600)',
                                                }}
                                            >
                                                Адрес закреплён за сайтом и не
                                                меняется: заголовок, текст и
                                                описание этой страницы выводятся
                                                по ссылке выше.
                                            </p>
                                        ) : (
                                            <div
                                                style={{
                                                    display: 'flex',
                                                    gap: 6,
                                                    marginTop: 8,
                                                }}
                                            >
                                                <Input
                                                    value={data.slug}
                                                    onChange={(e) => {
                                                        setSlugTouched(true);
                                                        setData(
                                                            'slug',
                                                            e.target.value,
                                                        );
                                                    }}
                                                    placeholder="about"
                                                    className="ui-mono"
                                                    style={{ fontSize: 12.5 }}
                                                    aria-label="Адрес страницы"
                                                />
                                                <button
                                                    type="button"
                                                    className="wp-quick-slug-btn"
                                                    onClick={handleAutoSlug}
                                                    title="Составить адрес из заголовка"
                                                >
                                                    <Sparkles size={14} />
                                                    <span>Авто</span>
                                                </button>
                                            </div>
                                        )}
                                        {fieldError('slug') && (
                                            <div className="wp-field-error">
                                                {fieldError('slug')}
                                            </div>
                                        )}
                                    </div>

                                    {/* Hierarchy & Order Attributes */}
                                    <div className="wp-inspector-section">
                                        <div className="wp-inspector-section-title">
                                            Атрибуты страницы
                                        </div>
                                        <div className="wp-inspector-field">
                                            <Field
                                                label="Родительская страница"
                                                error={fieldError('parent_id')}
                                            >
                                                <Select
                                                    value={
                                                        data.parent_id === ''
                                                            ? ''
                                                            : String(
                                                                  data.parent_id,
                                                              )
                                                    }
                                                    options={parentOptions}
                                                    onChange={(e) =>
                                                        setData(
                                                            'parent_id',
                                                            e.target.value ===
                                                                ''
                                                                ? ''
                                                                : Number(
                                                                      e.target
                                                                          .value,
                                                                  ),
                                                        )
                                                    }
                                                />
                                            </Field>
                                        </div>

                                        <div
                                            className="wp-inspector-field"
                                            style={{ marginTop: 12 }}
                                        >
                                            <Field
                                                label="Порядок сортировки"
                                                hint="Меньше — выше в списке страниц"
                                                error={fieldError('sort')}
                                            >
                                                <Input
                                                    type="number"
                                                    min={0}
                                                    value={String(data.sort)}
                                                    onChange={(e) =>
                                                        setData(
                                                            'sort',
                                                            Number(
                                                                e.target.value,
                                                            ) || 0,
                                                        )
                                                    }
                                                />
                                            </Field>
                                        </div>
                                    </div>
                                </>
                            )}

                            {sidebarTab === 'seo' && (
                                <div className="wp-inspector-section">
                                    <div className="wp-inspector-section-title">
                                        Поисковая выдача (Google / Yandex)
                                    </div>

                                    {/* Google SERP Snippet Preview */}
                                    <div className="wp-seo-preview-card">
                                        <div className="wp-seo-preview-url">
                                            {displayUrl(permalink)
                                                .split('/')
                                                .join(' › ')}
                                        </div>
                                        <div className="wp-seo-preview-title">
                                            {data.seo_title[lang]?.trim() ||
                                                data.title[lang]?.trim() ||
                                                'Заголовок страницы — КЧС Таджикистан'}
                                        </div>
                                        <div className="wp-seo-preview-desc">
                                            {data.seo_description[
                                                lang
                                            ]?.trim() ||
                                                'Официальная страница Комитета по чрезвычайным ситуациям и гражданской обороне при Правительстве Республики Таджикистан.'}
                                        </div>
                                    </div>

                                    <div className="wp-inspector-field">
                                        <Field
                                            label={`SEO Title (${lang.toUpperCase()})`}
                                            hint={`${data.seo_title[lang]?.length || 0} / 70 знаков`}
                                            error={fieldError(
                                                `seo_title.${lang}`,
                                            )}
                                        >
                                            <Input
                                                value={data.seo_title[lang]}
                                                onChange={(e) =>
                                                    setLocaleField(
                                                        'seo_title',
                                                        e.target.value,
                                                    )
                                                }
                                                maxLength={70}
                                                placeholder={
                                                    data.title[lang] ||
                                                    'Заголовок для поисковиков'
                                                }
                                            />
                                        </Field>
                                    </div>

                                    <div
                                        className="wp-inspector-field"
                                        style={{ marginTop: 12 }}
                                    >
                                        <Field
                                            label={`SEO Description (${lang.toUpperCase()})`}
                                            hint={`${data.seo_description[lang]?.length || 0} / 180 знаков`}
                                            error={fieldError(
                                                `seo_description.${lang}`,
                                            )}
                                        >
                                            <Textarea
                                                value={
                                                    data.seo_description[lang]
                                                }
                                                onChange={(e) =>
                                                    setLocaleField(
                                                        'seo_description',
                                                        e.target.value,
                                                    )
                                                }
                                                maxLength={180}
                                                style={{ minHeight: 84 }}
                                                placeholder="Краткое описание страницы в поисковой выдаче…"
                                            />
                                        </Field>
                                    </div>
                                </div>
                            )}
                        </div>
                    </aside>
                )}
            </div>
        </EditorialFormShell>
    );
}
