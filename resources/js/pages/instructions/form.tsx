import { useForm } from '@inertiajs/react';
import {
    ExternalLink,
    Images,
    Plus,
    Sliders,
    Sparkles,
    Upload,
    X,
} from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { EditorialFormShell } from '@/cms/EditorialFormShell';
import type { PendingChangeInfo } from '@/cms/EditorialFormShell';
import { useCan } from '@/lib/auth';
import type { ContentLocale, ContentStatus } from '@/lib/domain';
import {
    displayUrl,
    publicPath,
    siteUrl,
    usePublicSiteUrl,
} from '@/lib/public-site';
import { languageChecks } from '@/lib/publication-languages';
import { slugify } from '@/lib/slugify';
import { index, store, unpublish, update } from '@/routes/instructions';
import { AttachmentsField } from '@/ui/AttachmentsField';
import { Button, IconButton } from '@/ui/Button';
import { Checkbox, Field, Input, Select, Textarea } from '@/ui/Field';
import { MediaPicker } from '@/ui/MediaPicker';
import type { MediaItem } from '@/ui/MediaPicker';
import { ReadinessWidget } from '@/ui/ReadinessWidget';
import { RichEditor } from '@/ui/RichEditor';

type LocaleMap = { ru: string; tg: string; en: string };
type StepMap = { ru: string[]; tg: string[]; en: string[] };
type SectionKey = 'before' | 'during' | 'after' | 'prohibited';
type Sections = Record<SectionKey, StepMap>;
type PublishMode = 'now' | 'review';

interface Option {
    value: string;
    label: string;
}

interface InstructionData {
    id: number;
    name: LocaleMap;
    summary: LocaleMap;
    key_point: LocaleMap;
    attachments?: { id: number; title: string; ext: string; size: string }[];
    body: LocaleMap;
    slug: string | null;
    status: ContentStatus;
    hazard_type: string | null;
    is_priority: boolean;
    sort: number;
    sections: Sections;
    image_url: string | null;
    languages: Record<string, number>;
    updated_at: string;
    preview_url: string;
}

interface Props {
    instruction: InstructionData | null;
    reference: {
        hazards: Option[];
        authors: Option[];
        sectionKeys: { key: SectionKey; label: string }[];
    };
    /** A proposal waiting for approval on this live material. */
    pending_change?: PendingChangeInfo | null;
    /** Saves of this user become proposals (live material, no publish right). */
    changes_need_approval?: boolean;
}

const EMPTY: LocaleMap = { ru: '', tg: '', en: '' };

function emptySections(): Sections {
    return {
        before: { ru: [], tg: [], en: [] },
        during: { ru: [], tg: [], en: [] },
        after: { ru: [], tg: [], en: [] },
        prohibited: { ru: [], tg: [], en: [] },
    };
}

export default function InstructionForm({
    instruction,
    reference,
    pending_change = null,
    changes_need_approval = false,
}: Props) {
    const can = useCan();
    const isEdit = !!instruction;
    const [lang, setLang] = useState<ContentLocale>('ru');
    const [sidebarOpen, setSidebarOpen] = useState(true);
    const [sidebarTab, setSidebarTab] = useState<'document' | 'media'>(
        'document',
    );
    const [imagePicker, setImagePicker] = useState(false);
    const [imagePreview, setImagePreview] = useState<string | null>(null);
    const imageFileRef = useRef<HTMLInputElement>(null);
    const nameRef = useRef<HTMLTextAreaElement>(null);
    // A new instruction's address follows its Russian name until the editor
    // types one (it used to stop after the first letter); an existing one
    // never changes address on its own — links to it would break.
    const [slugTouched, setSlugTouched] = useState(isEdit);
    const summaryRef = useRef<HTMLTextAreaElement>(null);

    const form = useForm({
        name: { ...EMPTY, ...instruction?.name } as LocaleMap,
        summary: { ...EMPTY, ...instruction?.summary } as LocaleMap,
        key_point: { ...EMPTY, ...instruction?.key_point } as LocaleMap,
        attachments: [] as File[],
        attachments_remove: [] as number[],
        body: { ...EMPTY, ...instruction?.body } as LocaleMap,
        slug: instruction?.slug ?? '',
        hazard_type: (instruction?.hazard_type ?? '') as string,
        is_priority: instruction?.is_priority ?? false,
        sort: instruction?.sort ?? 0,
        sections: (instruction?.sections ?? emptySections()) as Sections,
        image: null as File | null,
        image_media_id: null as number | null,
        image_remove: false,
        publish_mode: 'review' as PublishMode,
        action: 'draft' as 'draft' | 'submit',
    });
    const { data, setData, processing, errors, isDirty } = form;

    const fieldError = (key: string): string | undefined =>
        (errors as Record<string, string | undefined>)[key];

    // Auto-resize textareas
    useEffect(() => {
        if (nameRef.current) {
            nameRef.current.style.height = 'auto';
            nameRef.current.style.height = `${nameRef.current.scrollHeight}px`;
        }
    }, [data.name, lang]);

    useEffect(() => {
        if (summaryRef.current) {
            summaryRef.current.style.height = 'auto';
            summaryRef.current.style.height = `${summaryRef.current.scrollHeight}px`;
        }
    }, [data.summary, lang]);

    const pickImageFromLibrary = (item: MediaItem) => {
        setData('image', null);
        setData('image_media_id', item.id);
        setData('image_remove', false);
        setImagePreview(item.url);
        setImagePicker(false);
    };

    // Превью: свежий выбор (файл/медиатека) приоритетнее существующего файла.
    const imageSrc =
        imagePreview ??
        (instruction?.image_url && !data.image_remove
            ? instruction.image_url
            : null);

    const completeness = (locale: ContentLocale): number => {
        const filled = (['name', 'summary'] as const).filter(
            (f) => (data[f][locale] ?? '').trim() !== '',
        ).length;

        return Math.round((filled / 2) * 100);
    };
    const compAll = {
        tg: completeness('tg'),
        ru: completeness('ru'),
        en: completeness('en'),
    };

    const setLocaleField = (
        field: 'name' | 'summary' | 'key_point' | 'body',
        value: string,
    ) => {
        setData(field, { ...data[field], [lang]: value });
    };

    // 1-Click Copy across languages (WordPress Gutenberg UX)
    const handleCopyLocale = (from: ContentLocale, to: ContentLocale) => {
        setData((prev) => {
            const nextSections = { ...prev.sections };

            for (const { key } of reference.sectionKeys) {
                nextSections[key] = {
                    ...nextSections[key],
                    [to]: [...(prev.sections[key]?.[from] ?? [])],
                };
            }

            return {
                ...prev,
                name: { ...prev.name, [to]: prev.name[from] },
                summary: { ...prev.summary, [to]: prev.summary[from] },
                key_point: { ...prev.key_point, [to]: prev.key_point[from] },
                body: { ...prev.body, [to]: prev.body[from] },
                sections: nextSections,
            };
        });
    };

    // Auto-slugify
    const handleAutoSlug = () => {
        const source =
            data.name.ru?.trim() ||
            data.name.tg?.trim() ||
            data.name.en?.trim() ||
            '';

        if (source) {
            setData('slug', slugify(source));
        }
    };

    // Immutably update the step list of one section for the active language.
    const mutateSteps = (
        section: SectionKey,
        next: (steps: string[]) => string[],
    ) => {
        setData('sections', {
            ...data.sections,
            [section]: {
                ...data.sections[section],
                [lang]: next(data.sections[section][lang]),
            },
        });
    };

    const addStep = (section: SectionKey) =>
        mutateSteps(section, (steps) => [...steps, '']);
    const updateStep = (section: SectionKey, i: number, value: string) =>
        mutateSteps(section, (steps) =>
            steps.map((s, idx) => (idx === i ? value : s)),
        );
    const removeStep = (section: SectionKey, i: number) =>
        mutateSteps(section, (steps) => steps.filter((_, idx) => idx !== i));

    // Readiness score calculation
    const hasName = data.name[lang]?.trim().length > 0;
    const hasSummary = data.summary[lang]?.trim().length > 0;
    const hasKeyPoint = data.key_point[lang]?.trim().length > 0;
    const totalSteps = reference.sectionKeys.reduce(
        (acc, { key }) =>
            acc +
            (data.sections[key]?.[lang]?.filter((s) => s.trim().length > 0)
                .length || 0),
        0,
    );
    const hasSteps = totalSteps > 0;
    const hasHazard = Boolean(data.hazard_type);
    const hasBilingual = Boolean(data.name.tg?.trim() && data.name.ru?.trim());

    let readinessScore = 0;

    if (hasName) {
        readinessScore += 25;
    }

    if (hasSummary) {
        readinessScore += 15;
    }

    if (hasKeyPoint) {
        readinessScore += 15;
    }

    if (hasSteps) {
        readinessScore += 20;
    }

    if (hasHazard) {
        readinessScore += 15;
    }

    if (hasBilingual) {
        readinessScore += 10;
    }

    const readinessItems = [
        {
            id: 'name',
            label: `Название (${lang.toUpperCase()})`,
            done: hasName,
        },
        {
            id: 'summary',
            label: 'Краткое описание',
            done: hasSummary,
        },
        {
            id: 'keypoint',
            label: 'Главное за 10 сек',
            done: hasKeyPoint,
        },
        {
            id: 'steps',
            label: `Шаги безопасности (${totalSteps})`,
            done: hasSteps,
        },
        {
            id: 'hazard',
            label: 'Тип ЧС / опасности',
            done: hasHazard,
        },
        {
            id: 'bilingual',
            label: 'Двуязычие (TG + RU)',
            done: hasBilingual,
        },
    ];

    const publicSiteUrl = usePublicSiteUrl();
    const permalink = siteUrl(
        publicSiteUrl,
        publicPath('instruction', data.slug || '…') ?? '/guides',
        lang,
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
                      _editorial_version: instruction!.updated_at,
                  }
                : {}),
        }));

        form.post(isEdit ? update.url(instruction!.id) : store.url(), {
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
            unpublishUrl={isEdit ? unpublish.url(instruction!.id) : undefined}
            pendingChange={pending_change}
            changesNeedApproval={changes_need_approval}
            canApprove={can('instructions.approve')}
            variant="gutenberg"
            onCopyLocale={handleCopyLocale}
            title={isEdit ? 'Редактирование инструкции' : 'Новая инструкция'}
            subtitle="Правила безопасности гражданам при ЧС: блоки «До / Во время / После / Нельзя»."
            backLabel="Инструкции"
            backHref={index.url()}
            status={instruction?.status}
            language={{
                active: lang,
                onChange: setLang,
                completeness: compAll,
            }}
            errors={errors}
            isDirty={isDirty}
            processing={processing}
            canPublish={can('instructions.publish')}
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
                contentType: 'instructions',
                contentId: instruction?.id ?? null,
                baseVersion: instruction?.updated_at ?? null,
                data,
                onRecover: (recovered) =>
                    form.setData({ ...data, ...recovered }),
            }}
            preview={{
                locales: {
                    tg: {
                        title: data.name.tg,
                        summary: data.summary.tg,
                        body: data.body.tg,
                    },
                    ru: {
                        title: data.name.ru,
                        summary: data.summary.ru,
                        body: data.body.ru,
                    },
                    en: {
                        title: data.name.en,
                        summary: data.summary.en,
                        body: data.body.en,
                    },
                },
                titleWord: 'названия',
                imageUrl: imageSrc,
                signedUrl: instruction?.preview_url,
                checklist: [
                    ...languageChecks(compAll, data.name, 'названия'),
                    {
                        label: 'Тип опасности выбран',
                        ok: data.hazard_type !== '',
                    },
                    {
                        label: 'Добавлены шаги безопасности',
                        ok: hasSteps,
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
                                ref={nameRef}
                                id={`instruction-name-${lang}`}
                                value={data.name[lang]}
                                onChange={(e) => {
                                    setLocaleField('name', e.target.value);

                                    if (!slugTouched && lang === 'ru') {
                                        setData((prev) => ({
                                            ...prev,
                                            slug: slugify(e.target.value),
                                        }));
                                    }
                                }}
                                placeholder={
                                    lang === 'ru'
                                        ? 'Название инструкции по безопасности…'
                                        : lang === 'tg'
                                          ? 'Номи дастурамали бехатарӣ…'
                                          : 'Safety instruction title…'
                                }
                                rows={1}
                                className="wp-title-input"
                                maxLength={255}
                            />
                            {(fieldError('name') ||
                                fieldError(`name.${lang}`)) && (
                                <div className="wp-field-error">
                                    {fieldError('name') ||
                                        fieldError(`name.${lang}`)}
                                </div>
                            )}
                        </div>

                        {/* Summary field */}
                        <div className="wp-lead-wrapper">
                            <textarea
                                ref={summaryRef}
                                id={`instruction-summary-${lang}`}
                                value={data.summary[lang]}
                                onChange={(e) =>
                                    setLocaleField('summary', e.target.value)
                                }
                                placeholder={
                                    lang === 'ru'
                                        ? 'Краткое описание ситуации и правил для каталога…'
                                        : lang === 'tg'
                                          ? 'Тавсифи мухтасари вазъият барои феҳрист…'
                                          : 'Brief situation overview for catalog…'
                                }
                                rows={2}
                                className="wp-lead-input"
                                maxLength={1000}
                            />
                            {(fieldError('summary') ||
                                fieldError(`summary.${lang}`)) && (
                                <div className="wp-field-error">
                                    {fieldError('summary') ||
                                        fieldError(`summary.${lang}`)}
                                </div>
                            )}
                        </div>

                        {/* ⚡ Emergency 10-Second Key Point */}
                        <div
                            style={{
                                background:
                                    'color-mix(in srgb, var(--brand) 6%, transparent)',
                                border: '1px solid color-mix(in srgb, var(--brand) 25%, transparent)',
                                borderRadius: 'var(--radius-md)',
                                padding: '14px 18px',
                                marginBottom: 28,
                            }}
                        >
                            <div
                                style={{
                                    display: 'flex',
                                    alignItems: 'center',
                                    gap: 8,
                                    marginBottom: 8,
                                }}
                            >
                                <span
                                    style={{
                                        display: 'inline-flex',
                                        alignItems: 'center',
                                        justifyContent: 'center',
                                        width: 22,
                                        height: 22,
                                        borderRadius: '50%',
                                        background: 'var(--brand)',
                                        color: '#fff',
                                        fontSize: 12,
                                        fontWeight: 700,
                                    }}
                                >
                                    ⚡
                                </span>
                                <strong
                                    style={{
                                        fontSize: 13.5,
                                        color: 'var(--brand-800)',
                                    }}
                                >
                                    Главное за 10 секунд
                                </strong>
                                <span
                                    style={{
                                        fontSize: 12,
                                        color: 'var(--color-neutral-500)',
                                        marginLeft: 'auto',
                                    }}
                                >
                                    Первое действие при ЧС (выделяется на
                                    карточке)
                                </span>
                            </div>
                            <Textarea
                                value={data.key_point[lang]}
                                onChange={(e) =>
                                    setLocaleField('key_point', e.target.value)
                                }
                                placeholder="Что делать прямо сейчас в первые секунды (не паниковать, лечь, перекрыть газ…)"
                                style={{ minHeight: 56 }}
                                maxLength={300}
                            />
                            {(fieldError('key_point') ||
                                fieldError(`key_point.${lang}`)) && (
                                <div className="wp-field-error">
                                    {fieldError('key_point') ||
                                        fieldError(`key_point.${lang}`)}
                                </div>
                            )}
                        </div>

                        {/* Step sections */}
                        <div style={{ marginBottom: 32 }}>
                            <div
                                style={{
                                    display: 'flex',
                                    alignItems: 'center',
                                    justifyContent: 'space-between',
                                    marginBottom: 16,
                                    borderBottom:
                                        '1px solid var(--color-divider)',
                                    paddingBottom: 10,
                                }}
                            >
                                <div>
                                    <h3
                                        style={{
                                            margin: 0,
                                            fontSize: 16,
                                            fontWeight: 700,
                                        }}
                                    >
                                        Шаги инструкции
                                    </h3>
                                    <p
                                        style={{
                                            margin: '2px 0 0',
                                            fontSize: 12.5,
                                            color: 'var(--color-neutral-600)',
                                        }}
                                    >
                                        Язык блоков: <b>{lang.toUpperCase()}</b>
                                        . Заполните рекомендации по ключевым
                                        фазам ЧС.
                                    </p>
                                </div>
                            </div>

                            <div
                                style={{
                                    display: 'flex',
                                    flexDirection: 'column',
                                    gap: 20,
                                }}
                            >
                                {reference.sectionKeys.map(({ key, label }) => (
                                    <div
                                        key={key}
                                        style={{
                                            background:
                                                'var(--color-neutral-50)',
                                            border: '1px solid var(--color-divider)',
                                            borderRadius: 'var(--radius-md)',
                                            padding: '16px 18px',
                                        }}
                                    >
                                        <div
                                            style={{
                                                display: 'flex',
                                                alignItems: 'center',
                                                justifyContent: 'space-between',
                                                marginBottom: 10,
                                            }}
                                        >
                                            <span
                                                style={{
                                                    fontFamily:
                                                        'var(--font-heading)',
                                                    fontWeight: 600,
                                                    fontSize: 14,
                                                    color:
                                                        key === 'prohibited'
                                                            ? 'var(--danger)'
                                                            : 'var(--color-text)',
                                                }}
                                            >
                                                {label}
                                            </span>
                                            <Button
                                                variant="secondary"
                                                size="sm"
                                                icon={
                                                    <Plus
                                                        size={14}
                                                        strokeWidth={2}
                                                    />
                                                }
                                                onClick={() => addStep(key)}
                                            >
                                                Добавить шаг
                                            </Button>
                                        </div>

                                        {data.sections[key][lang].length ===
                                        0 ? (
                                            <p
                                                style={{
                                                    margin: 0,
                                                    fontSize: 12.5,
                                                    color: 'var(--color-neutral-400)',
                                                    fontStyle: 'italic',
                                                }}
                                            >
                                                Шаги не добавлены. Нажмите
                                                «Добавить шаг», чтобы внести
                                                рекомендацию.
                                            </p>
                                        ) : (
                                            <div
                                                style={{
                                                    display: 'flex',
                                                    flexDirection: 'column',
                                                    gap: 8,
                                                }}
                                            >
                                                {data.sections[key][lang].map(
                                                    (step, i) => (
                                                        <div
                                                            key={i}
                                                            style={{
                                                                display: 'flex',
                                                                gap: 8,
                                                                alignItems:
                                                                    'flex-start',
                                                            }}
                                                        >
                                                            <span
                                                                className="ui-mono"
                                                                style={{
                                                                    width: 24,
                                                                    paddingTop: 8,
                                                                    fontSize: 12.5,
                                                                    fontWeight: 600,
                                                                    color: 'var(--color-neutral-500)',
                                                                }}
                                                            >
                                                                {String(
                                                                    i + 1,
                                                                ).padStart(
                                                                    2,
                                                                    '0',
                                                                )}
                                                            </span>
                                                            <Textarea
                                                                value={step}
                                                                onChange={(e) =>
                                                                    updateStep(
                                                                        key,
                                                                        i,
                                                                        e.target
                                                                            .value,
                                                                    )
                                                                }
                                                                style={{
                                                                    minHeight: 44,
                                                                    flex: 1,
                                                                }}
                                                                maxLength={1000}
                                                                placeholder={`Описание шага ${i + 1}…`}
                                                            />
                                                            <IconButton
                                                                label="Удалить шаг"
                                                                variant="ghost"
                                                                onClick={() =>
                                                                    removeStep(
                                                                        key,
                                                                        i,
                                                                    )
                                                                }
                                                            >
                                                                <X
                                                                    size={15}
                                                                    strokeWidth={
                                                                        1.5
                                                                    }
                                                                />
                                                            </IconButton>
                                                        </div>
                                                    ),
                                                )}
                                            </div>
                                        )}
                                    </div>
                                ))}
                            </div>
                        </div>

                        {/* Detailed text (RichEditor) */}
                        <div className="wp-body-wrapper">
                            <div
                                style={{
                                    display: 'flex',
                                    justifyContent: 'space-between',
                                    alignItems: 'center',
                                    marginBottom: 10,
                                }}
                            >
                                <span
                                    style={{
                                        fontWeight: 600,
                                        fontSize: 14,
                                    }}
                                >
                                    Подробное описание
                                </span>
                                <span
                                    style={{
                                        fontSize: 12,
                                        color: 'var(--color-neutral-500)',
                                    }}
                                >
                                    Язык: <b>{lang.toUpperCase()}</b>
                                </span>
                            </div>
                            <RichEditor
                                key={lang}
                                value={data.body[lang]}
                                onChange={(html) =>
                                    setLocaleField('body', html)
                                }
                                placeholder="Развёрнутое описание, контекст, ссылки на документы и спасательные службы…"
                            />
                            {(fieldError('body') ||
                                fieldError(`body.${lang}`)) && (
                                <div className="wp-field-error">
                                    {fieldError('body') ||
                                        fieldError(`body.${lang}`)}
                                </div>
                            )}
                        </div>

                        {/* Attachments */}
                        <div
                            style={{
                                marginTop: 28,
                                borderTop: '1px dashed var(--color-divider)',
                                paddingTop: 20,
                            }}
                        >
                            <AttachmentsField
                                existing={instruction?.attachments ?? []}
                                added={data.attachments}
                                removed={data.attachments_remove}
                                error={fieldError('attachments')}
                                onAdd={(files) =>
                                    setData('attachments', [
                                        ...data.attachments,
                                        ...files,
                                    ])
                                }
                                onToggleRemove={(id) =>
                                    setData(
                                        'attachments_remove',
                                        data.attachments_remove.includes(id)
                                            ? data.attachments_remove.filter(
                                                  (x) => x !== id,
                                              )
                                            : [...data.attachments_remove, id],
                                    )
                                }
                            />
                        </div>
                    </div>
                </main>

                {/* ------------------------------------------- Inspector Sidebar */}
                {sidebarOpen && (
                    <aside
                        className="wp-inspector"
                        aria-label="Параметры инструкции"
                    >
                        {/* Header */}
                        <div className="wp-inspector-header">
                            <span className="wp-inspector-title">
                                Инспектор инструкции
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
                                Параметры
                            </button>
                            <button
                                type="button"
                                role="tab"
                                aria-selected={sidebarTab === 'media'}
                                className={`wp-inspector-tab ${sidebarTab === 'media' ? 'is-active' : ''}`}
                                onClick={() => setSidebarTab('media')}
                            >
                                Иллюстрация
                            </button>
                        </div>

                        {/* Inspector Body */}
                        <div className="wp-inspector-body">
                            {sidebarTab === 'document' && (
                                <>
                                    {/* Traffic-light Readiness Score Widget */}
                                    <div className="wp-inspector-section">
                                        <ReadinessWidget
                                            score={readinessScore}
                                            items={readinessItems}
                                        />
                                    </div>

                                    {/* Permalink & Slug with Auto-generate */}
                                    <div className="wp-inspector-section">
                                        <div className="wp-inspector-section-title">
                                            Адрес инструкции (Slug)
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
                                                placeholder="deystviya-pri-zemletryasenii"
                                                className="ui-mono"
                                                style={{ fontSize: 12.5 }}
                                            />
                                            <button
                                                type="button"
                                                className="wp-quick-slug-btn"
                                                onClick={handleAutoSlug}
                                                title="Сгенерировать slug из названия"
                                            >
                                                <Sparkles size={14} />
                                                <span>Авто</span>
                                            </button>
                                        </div>
                                        {fieldError('slug') && (
                                            <div className="wp-field-error">
                                                {fieldError('slug')}
                                            </div>
                                        )}
                                    </div>

                                    {/* Hazard Type & Display Settings */}
                                    <div className="wp-inspector-section">
                                        <div className="wp-inspector-section-title">
                                            Классификация и показ
                                        </div>
                                        <div className="wp-inspector-field">
                                            <Field
                                                label="Тип опасности"
                                                error={fieldError(
                                                    'hazard_type',
                                                )}
                                            >
                                                <Select
                                                    value={data.hazard_type}
                                                    onChange={(e) =>
                                                        setData(
                                                            'hazard_type',
                                                            e.target.value,
                                                        )
                                                    }
                                                    placeholder="Выберите тип опасности"
                                                    options={reference.hazards}
                                                />
                                            </Field>
                                        </div>

                                        <div
                                            className="wp-inspector-field"
                                            style={{ marginTop: 12 }}
                                        >
                                            <Field
                                                label="Порядок сортировки"
                                                hint="Меньше — выше в каталоге"
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

                                        <div style={{ marginTop: 14 }}>
                                            <Checkbox
                                                label="Закрепить (приоритетная карточка в каталоге)"
                                                checked={data.is_priority}
                                                onChange={(e) =>
                                                    setData(
                                                        'is_priority',
                                                        e.target.checked,
                                                    )
                                                }
                                            />
                                        </div>
                                    </div>
                                </>
                            )}

                            {sidebarTab === 'media' && (
                                <div className="wp-inspector-section">
                                    <div className="wp-inspector-section-title">
                                        Иллюстрация инструкции
                                    </div>
                                    {imageSrc ? (
                                        <div style={{ marginBottom: 12 }}>
                                            <img
                                                src={imageSrc}
                                                alt="Иллюстрация"
                                                style={{
                                                    width: '100%',
                                                    height: 180,
                                                    objectFit: 'cover',
                                                    borderRadius:
                                                        'var(--radius-md)',
                                                    border: '1px solid var(--color-divider)',
                                                }}
                                            />
                                        </div>
                                    ) : (
                                        <div
                                            style={{
                                                padding: '24px 16px',
                                                background:
                                                    'var(--color-neutral-100)',
                                                borderRadius:
                                                    'var(--radius-md)',
                                                border: '1px dashed var(--color-divider)',
                                                textAlign: 'center',
                                                fontSize: 12.5,
                                                color: 'var(--color-neutral-500)',
                                                marginBottom: 12,
                                            }}
                                        >
                                            Иллюстрация не выбрана
                                        </div>
                                    )}

                                    <input
                                        ref={imageFileRef}
                                        type="file"
                                        accept="image/png,image/jpeg,image/webp"
                                        hidden
                                        onChange={(e) => {
                                            const file =
                                                e.target.files?.[0] ?? null;

                                            if (file) {
                                                setData('image', file);
                                                setData('image_media_id', null);
                                                setData('image_remove', false);
                                                setImagePreview(
                                                    URL.createObjectURL(file),
                                                );
                                            }

                                            e.target.value = '';
                                        }}
                                    />

                                    <div
                                        style={{
                                            display: 'flex',
                                            gap: 8,
                                            flexWrap: 'wrap',
                                        }}
                                    >
                                        <Button
                                            variant="secondary"
                                            size="sm"
                                            icon={<Upload size={14} />}
                                            onClick={() =>
                                                imageFileRef.current?.click()
                                            }
                                        >
                                            Загрузить файл
                                        </Button>
                                        <Button
                                            variant="secondary"
                                            size="sm"
                                            icon={<Images size={14} />}
                                            onClick={() => setImagePicker(true)}
                                        >
                                            Из медиатеки
                                        </Button>
                                    </div>

                                    {fieldError('image') && (
                                        <div className="wp-field-error">
                                            {fieldError('image')}
                                        </div>
                                    )}

                                    {instruction?.image_url && (
                                        <div style={{ marginTop: 12 }}>
                                            <Checkbox
                                                label="Удалить текущее изображение"
                                                checked={data.image_remove}
                                                onChange={(e) =>
                                                    setData(
                                                        'image_remove',
                                                        e.target.checked,
                                                    )
                                                }
                                            />
                                        </div>
                                    )}

                                    <MediaPicker
                                        open={imagePicker}
                                        onClose={() => setImagePicker(false)}
                                        onSelect={pickImageFromLibrary}
                                    />
                                </div>
                            )}
                        </div>
                    </aside>
                )}
            </div>
        </EditorialFormShell>
    );
}
