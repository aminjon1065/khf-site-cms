import { useForm } from '@inertiajs/react';
import { FileText, Sliders, Wand2, X } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { EditorialFormShell } from '@/cms/EditorialFormShell';
import type { PendingChangeInfo } from '@/cms/EditorialFormShell';
import { useInspectorOpen } from '@/hooks/use-inspector-open';
import { useCan } from '@/lib/auth';
import { localeShort } from '@/lib/domain';
import type { ContentLocale, ContentStatus } from '@/lib/domain';
import { displayUrl, siteUrl, usePublicSiteUrl } from '@/lib/public-site';
import { languageChecks } from '@/lib/publication-languages';
import { slugify } from '@/lib/slugify';
import { index, store, unpublish, update } from '@/routes/announcements';
import { Button } from '@/ui/Button';
import { DatePicker, Field, Input, Select, Textarea } from '@/ui/Field';
import { ReadinessWidget } from '@/ui/ReadinessWidget';

type LocaleMap = { ru: string; tg: string; en: string };
type PublishMode = 'now' | 'review';

interface Option {
    value: string;
    label: string;
}

interface AnnouncementData {
    id: number;
    title: LocaleMap;
    body: LocaleMap;
    slug: string;
    kind: string;
    org: string | null;
    project_id: number | null;
    deadline: string | null;
    application_url: string | null;
    status: ContentStatus;
    is_open: boolean;
    updated_at: string;
    preview_url: string;
}

interface Props {
    announcement: AnnouncementData | null;
    reference: {
        kinds: Option[];
        authors: Option[];
        projects: Option[];
    };
    /** A proposal waiting for approval on this live material. */
    pending_change?: PendingChangeInfo | null;
    /** Saves of this user become proposals (live material, no publish right). */
    changes_need_approval?: boolean;
}

const EMPTY: LocaleMap = { ru: '', tg: '', en: '' };

export default function AnnouncementForm({
    announcement,
    reference,
    pending_change = null,
    changes_need_approval = false,
}: Props) {
    const can = useCan();
    const isEdit = !!announcement;
    const [lang, setLang] = useState<ContentLocale>('ru');
    const [sidebarOpen, setSidebarOpen] = useInspectorOpen();
    const titleRef = useRef<HTMLTextAreaElement>(null);

    const form = useForm({
        title: { ...EMPTY, ...announcement?.title } as LocaleMap,
        body: { ...EMPTY, ...announcement?.body } as LocaleMap,
        slug: announcement?.slug ?? '',
        kind: (announcement?.kind ?? 'vacancy') as string,
        org: announcement?.org ?? '',
        project_id: announcement?.project_id ?? '',
        deadline: announcement?.deadline ?? '',
        application_url: announcement?.application_url ?? '',
        publish_mode: 'review' as PublishMode,
        action: 'draft' as 'draft' | 'submit',
    });
    const { data, setData, processing, errors, isDirty } = form;

    const fieldError = (key: string): string | undefined =>
        (errors as Record<string, string | undefined>)[key];

    useEffect(() => {
        if (titleRef.current) {
            titleRef.current.style.height = 'auto';
            titleRef.current.style.height = `${titleRef.current.scrollHeight}px`;
        }
    }, [lang]);

    const compAll = {
        tg:
            (data.title.tg.trim() !== '' ? 50 : 0) +
            (data.body.tg.trim() !== '' ? 50 : 0),
        ru:
            (data.title.ru.trim() !== '' ? 50 : 0) +
            (data.body.ru.trim() !== '' ? 50 : 0),
        en:
            (data.title.en.trim() !== '' ? 50 : 0) +
            (data.body.en.trim() !== '' ? 50 : 0),
    };

    // A language version for the preview and the language checks: the site
    // shows it only with its title and text (PublicLocale).
    const versionOf = (locale: ContentLocale) => ({
        title: data.title[locale],
        body: data.body[locale],
        hasText: data.body[locale].trim() !== '',
    });
    const versions = {
        tg: versionOf('tg'),
        ru: versionOf('ru'),
        en: versionOf('en'),
    };

    const setLocaleField = (field: 'title' | 'body', value: string) =>
        setData(field, { ...data[field], [lang]: value });

    const handleCopyLocale = (from: ContentLocale, to: ContentLocale) => {
        setData('title', { ...data.title, [to]: data.title[from] });
        setData('body', { ...data.body, [to]: data.body[from] });
    };

    const handleAutoSlug = () => {
        const source =
            data.title[lang]?.trim() ||
            data.title.ru?.trim() ||
            data.title.tg?.trim() ||
            '';

        if (source) {
            setData('slug', slugify(source));
        }
    };

    // Оценка готовности материала (Traffic-light readiness score)
    const hasTitle = data.title[lang]?.trim().length > 0;
    const hasBody = data.body[lang]?.trim().length > 20;
    const hasDeadline = Boolean(data.deadline?.trim());
    const hasKind = Boolean(data.kind);
    const hasBilingual = Boolean(
        data.title.tg?.trim() && data.title.ru?.trim(),
    );

    let score = 0;

    if (hasTitle) {
        score += 30;
    }

    if (hasBody) {
        score += 30;
    }

    if (hasDeadline) {
        score += 15;
    }

    if (hasKind) {
        score += 10;
    }

    if (hasBilingual) {
        score += 15;
    }

    const readinessItems = [
        {
            id: 'title',
            label: `Заголовок (${localeShort[lang]})`,
            done: hasTitle,
        },
        {
            id: 'body',
            label: 'Описание объявления',
            done: hasBody,
        },
        {
            id: 'kind',
            label: 'Тип объявления',
            done: hasKind,
        },
        {
            id: 'deadline',
            label: 'Срок подачи заявок',
            done: hasDeadline,
        },
        {
            id: 'bilingual',
            label: 'Заполнено на таджикском и русском',
            done: hasBilingual,
        },
    ];

    const publicSiteUrl = usePublicSiteUrl();
    const permalinkPrefix = displayUrl(
        siteUrl(publicSiteUrl, '/announcements/', lang),
    );

    const submit = (
        action: 'draft' | 'submit',
        mode?: PublishMode,
        stay = false,
    ) => {
        form.transform((d) => ({
            ...d,
            action,
            publish_mode: mode ?? d.publish_mode,
            stay,
            ...(isEdit
                ? {
                      _method: 'put',
                      _editorial_version: announcement!.updated_at,
                  }
                : {}),
        }));

        form.post(isEdit ? update.url(announcement!.id) : store.url(), {
            forceFormData: true,
            preserveScroll: true,
            // Keep what the editor typed when validation fails (the page
            // would otherwise remount from server data and drop the errors);
            // a successful save leaving the editor starts clean.
            preserveState: stay ? true : 'errors',
        });
    };

    return (
        <EditorialFormShell
            unpublishUrl={isEdit ? unpublish.url(announcement!.id) : undefined}
            pendingChange={pending_change}
            changesNeedApproval={changes_need_approval}
            canApprove={can('announcements.approve')}
            variant="gutenberg"
            onCopyLocale={handleCopyLocale}
            title={isEdit ? 'Редактирование объявления' : 'Новое объявление'}
            subtitle="Вакансия или тендер. Приём заявок закрывается автоматически после срока."
            backLabel="Объявления"
            backHref={index.url()}
            status={announcement?.status}
            language={{
                active: lang,
                onChange: setLang,
                completeness: compAll,
            }}
            errors={errors}
            isDirty={isDirty}
            processing={processing}
            canPublish={can('announcements.publish')}
            onSaveDraft={() => submit('draft')}
            onSaveShortcut={() => submit('draft', undefined, true)}
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
                contentType: 'announcements',
                contentId: announcement?.id ?? null,
                baseVersion: announcement?.updated_at ?? null,
                data,
                onRecover: (recovered) =>
                    form.setData({ ...data, ...recovered }),
            }}
            preview={{
                locales: versions,
                signedUrl: announcement?.preview_url,
                checklist: [
                    ...languageChecks(compAll, versions),
                    {
                        label: 'Срок указан',
                        ok: data.deadline !== '',
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
                                id={`announcement-title-${lang}`}
                                value={data.title[lang]}
                                onChange={(e) => {
                                    setLocaleField('title', e.target.value);
                                    e.target.style.height = 'auto';
                                    e.target.style.height = `${e.target.scrollHeight}px`;
                                }}
                                placeholder={
                                    lang === 'ru'
                                        ? 'Например: Специалист службы 112...'
                                        : lang === 'tg'
                                          ? 'Заголовок на таджикском…'
                                          : 'Заголовок на английском…'
                                }
                                className="wp-title-input"
                                rows={1}
                                maxLength={255}
                                aria-label="Заголовок объявления"
                            />
                            {(fieldError('title') ??
                                fieldError(`title.${lang}`)) && (
                                <div className="wp-field-error">
                                    {fieldError('title') ??
                                        fieldError(`title.${lang}`)}
                                </div>
                            )}
                        </div>

                        {/* Body / Description field */}
                        <div className="wp-body-wrapper mt-4">
                            <Field
                                label="Описание и требования"
                                hint="Условия участия, требования к соискателям, контактные данные."
                            >
                                <Textarea
                                    value={data.body[lang]}
                                    onChange={(e) =>
                                        setLocaleField('body', e.target.value)
                                    }
                                    placeholder="Подробное описание вакансии или условий тендера..."
                                    style={{ minHeight: 280 }}
                                    maxLength={5000}
                                />
                            </Field>
                            {(fieldError('body') ??
                                fieldError(`body.${lang}`)) && (
                                <div className="wp-field-error">
                                    {fieldError('body') ??
                                        fieldError(`body.${lang}`)}
                                </div>
                            )}
                        </div>
                    </div>
                </main>

                {/* --------------------------------- Inspector Sidebar */}
                {sidebarOpen && (
                    <aside
                        className="wp-inspector"
                        aria-label="Панель настроек объявления"
                    >
                        <div className="wp-inspector-header">
                            <div className="wp-inspector-tabs" role="tablist">
                                <button
                                    type="button"
                                    role="tab"
                                    aria-selected="true"
                                    className="wp-inspector-tab is-active"
                                >
                                    <FileText size={15} />
                                    <span>Объявление</span>
                                </button>
                            </div>
                            <button
                                type="button"
                                className="wp-inspector-close"
                                title="Скрыть панель"
                                aria-label="Скрыть панель"
                                onClick={() => setSidebarOpen(false)}
                            >
                                <X size={16} strokeWidth={1.75} />
                            </button>
                        </div>

                        <div className="wp-inspector-body">
                            <div className="wp-inspector-sections">
                                {/* Оценка готовности */}
                                <ReadinessWidget
                                    score={score}
                                    items={readinessItems}
                                />

                                {/* 1. Публикация и ссылка */}
                                <section className="wp-inspector-section">
                                    <h4 className="wp-inspector-section-title">
                                        <span>Публикация</span>
                                    </h4>
                                    <div className="wp-inspector-section-content">
                                        <Field
                                            label="Адрес ссылки"
                                            htmlFor="announcement-slug"
                                            hint="Генерируется автоматически из заголовка."
                                            error={fieldError('slug')}
                                        >
                                            <div
                                                style={{
                                                    display: 'flex',
                                                    gap: 6,
                                                }}
                                            >
                                                <Input
                                                    id="announcement-slug"
                                                    value={data.slug}
                                                    onChange={(e) =>
                                                        setData(
                                                            'slug',
                                                            e.target.value,
                                                        )
                                                    }
                                                    hasError={
                                                        !!fieldError('slug')
                                                    }
                                                    placeholder="operator-sluzhby-112"
                                                    style={{ flex: 1 }}
                                                />
                                                <Button
                                                    type="button"
                                                    variant="secondary"
                                                    size="sm"
                                                    onClick={handleAutoSlug}
                                                    title="Составить адрес из заголовка"
                                                    icon={<Wand2 size={13} />}
                                                >
                                                    Из заголовка
                                                </Button>
                                            </div>
                                        </Field>

                                        <div className="wp-permalink-preview">
                                            <span className="wp-permalink-prefix">
                                                {permalinkPrefix}
                                            </span>
                                            <span className="wp-permalink-slug">
                                                {data.slug ||
                                                    'adres-obyavleniya'}
                                            </span>
                                        </div>
                                    </div>
                                </section>

                                {/* 2. Параметры объявления */}
                                <section className="wp-inspector-section">
                                    <h4 className="wp-inspector-section-title">
                                        <span>Параметры объявления</span>
                                    </h4>
                                    <div className="wp-inspector-section-content">
                                        <Field
                                            label="Тип объявления"
                                            required
                                            error={fieldError('kind')}
                                        >
                                            <Select
                                                value={data.kind}
                                                options={reference.kinds}
                                                onChange={(e) =>
                                                    setData(
                                                        'kind',
                                                        e.target.value,
                                                    )
                                                }
                                            />
                                        </Field>

                                        <Field
                                            label="Подразделение / проект"
                                            error={fieldError('org')}
                                            className="mt-3"
                                        >
                                            <Input
                                                value={data.org}
                                                onChange={(e) =>
                                                    setData(
                                                        'org',
                                                        e.target.value,
                                                    )
                                                }
                                                placeholder="Например: Отдел кадров"
                                                maxLength={255}
                                            />
                                        </Field>

                                        {data.kind === 'tender' && (
                                            <Field
                                                label="Проект"
                                                hint="Тендер появится в списке этого проекта."
                                                error={fieldError('project_id')}
                                                className="mt-3"
                                            >
                                                <Select
                                                    value={String(
                                                        data.project_id ?? '',
                                                    )}
                                                    options={[
                                                        {
                                                            value: '',
                                                            label: 'Вне проекта',
                                                        },
                                                        ...reference.projects,
                                                    ]}
                                                    onChange={(e) =>
                                                        setData(
                                                            'project_id',
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
                                        )}

                                        <Field
                                            label="Срок подачи заявок"
                                            hint="После этой даты приём закрывается автоматически."
                                            error={fieldError('deadline')}
                                            className="mt-3"
                                        >
                                            <DatePicker
                                                value={data.deadline}
                                                onChange={(e) =>
                                                    setData(
                                                        'deadline',
                                                        e.target.value,
                                                    )
                                                }
                                            />
                                        </Field>

                                        <Field
                                            label="Ссылка для подачи заявки"
                                            hint="Ссылка на страницу, адрес эл. почты (mailto:) или телефон (tel:)."
                                            error={fieldError(
                                                'application_url',
                                            )}
                                            className="mt-3"
                                        >
                                            <Input
                                                value={data.application_url}
                                                onChange={(e) =>
                                                    setData(
                                                        'application_url',
                                                        e.target.value,
                                                    )
                                                }
                                                placeholder="Например: https://khf.tj/contacts"
                                                maxLength={2048}
                                            />
                                        </Field>
                                    </div>
                                </section>
                            </div>
                        </div>
                    </aside>
                )}
            </div>
        </EditorialFormShell>
    );
}
