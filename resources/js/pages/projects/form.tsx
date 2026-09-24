import { useForm } from '@inertiajs/react';
import { FolderKanban, Plus, Sliders, X } from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import { EditorCanvasBlock } from '@/cms/EditorCanvasBlock';
import { EditorialFormShell } from '@/cms/EditorialFormShell';
import type { PendingChangeInfo } from '@/cms/EditorialFormShell';
import {
    CoverField,
    EditorInspector,
    InspectorSection,
    SlugField,
} from '@/cms/EditorInspector';
import { useInspectorOpen } from '@/hooks/use-inspector-open';
import { useCan } from '@/lib/auth';
import { localeShort } from '@/lib/domain';
import type { ContentLocale, ContentStatus } from '@/lib/domain';
import { displayUrl, siteUrl, usePublicSiteUrl } from '@/lib/public-site';
import { hasRichText, languageChecks } from '@/lib/publication-languages';
import { slugify } from '@/lib/slugify';
import { index, store, unpublish, update } from '@/routes/projects';
import { Button, IconButton } from '@/ui/Button';
import { Field, Input, Select, Textarea } from '@/ui/Field';
import { MediaPicker } from '@/ui/MediaPicker';
import type { MediaItem } from '@/ui/MediaPicker';
import { ReadinessWidget } from '@/ui/ReadinessWidget';
import { RichEditor } from '@/ui/RichEditor';

type LocaleMap = { ru: string; tg: string; en: string };
type GoalMap = { ru: string[]; tg: string[]; en: string[] };
type TimelineItem = { date: string; text: string; tone: string };
type Direction = { address: string; phone: string; email: string };
type PublishMode = 'now' | 'review';

interface Option {
    value: string;
    label: string;
}

interface ProjectData {
    id: number;
    title: LocaleMap;
    summary: LocaleMap;
    body: LocaleMap;
    slug: string | null;
    status: ContentStatus;
    lifecycle_status: string;
    code: string | null;
    years: string | null;
    customer: string | null;
    partner: string | null;
    budget: string | null;
    goals: GoalMap;
    timeline: TimelineItem[];
    direction: Direction;
    cover_url: string | null;
    sort: number;
    updated_at: string;
    preview_url: string;
}

interface Props {
    project: ProjectData | null;
    reference: {
        lifecycles: Option[];
        authors: Option[];
    };
    /** A proposal waiting for approval on this live material. */
    pending_change?: PendingChangeInfo | null;
    /** Saves of this user become proposals (live material, no publish right). */
    changes_need_approval?: boolean;
}

const EMPTY: LocaleMap = { ru: '', tg: '', en: '' };
const CONTENT_FIELDS: ('title' | 'summary' | 'body')[] = [
    'title',
    'summary',
    'body',
];
const TONE_OPTIONS: Option[] = [
    { value: 'success', label: 'Выполнено' },
    { value: 'info', label: 'В плане' },
    { value: 'warning', label: 'Внимание' },
    { value: 'neutral', label: 'Обычный' },
];

function emptyGoals(): GoalMap {
    return { ru: [], tg: [], en: [] };
}

/** Grows a textarea with its text, as the editor's title and lead do. */
function fitHeight(element: HTMLTextAreaElement | null): void {
    if (element) {
        element.style.height = 'auto';
        element.style.height = `${element.scrollHeight}px`;
    }
}

export default function ProjectForm({
    project,
    reference,
    pending_change = null,
    changes_need_approval = false,
}: Props) {
    const can = useCan();
    const isEdit = !!project;
    const publicSiteUrl = usePublicSiteUrl();
    const [lang, setLang] = useState<ContentLocale>('ru');
    const [sidebarOpen, setSidebarOpen] = useInspectorOpen();
    const [coverPicker, setCoverPicker] = useState(false);
    // A cover picked from the media library; a file from the computer is
    // previewed from the form data below.
    const [coverPreview, setCoverPreview] = useState<string | null>(null);
    const titleRef = useRef<HTMLTextAreaElement>(null);
    const summaryRef = useRef<HTMLTextAreaElement>(null);

    const form = useForm({
        title: { ...EMPTY, ...project?.title } as LocaleMap,
        summary: { ...EMPTY, ...project?.summary } as LocaleMap,
        body: { ...EMPTY, ...project?.body } as LocaleMap,
        slug: project?.slug ?? '',
        lifecycle_status: (project?.lifecycle_status ??
            'preparation') as string,
        code: project?.code ?? '',
        years: project?.years ?? '',
        customer: project?.customer ?? 'КЧС Республики Таджикистан',
        partner: project?.partner ?? '',
        budget: project?.budget ?? '',
        sort: project?.sort ?? 0,
        goals: (project?.goals ?? emptyGoals()) as GoalMap,
        timeline: (project?.timeline ?? []) as TimelineItem[],
        direction: (project?.direction ?? {
            address: '',
            phone: '',
            email: '',
        }) as Direction,
        cover: null as File | null,
        cover_media_id: null as number | null,
        cover_remove: false,
        publish_mode: 'review' as PublishMode,
        action: 'draft' as 'draft' | 'submit',
    });
    const { data, setData, processing, errors, isDirty } = form;

    const fieldError = (key: string): string | undefined =>
        (errors as Record<string, string | undefined>)[key];

    useEffect(() => {
        fitHeight(titleRef.current);
        fitHeight(summaryRef.current);
    }, [lang]);

    const coverFileUrl = useMemo(
        () => (data.cover ? URL.createObjectURL(data.cover) : null),
        [data.cover],
    );

    useEffect(
        () => () => {
            if (coverFileUrl) {
                URL.revokeObjectURL(coverFileUrl);
            }
        },
        [coverFileUrl],
    );

    // The fresh pick (a file or the library) wins over the saved cover; a
    // cover marked for removal isn't shown.
    const coverSrc =
        coverFileUrl ??
        coverPreview ??
        (project?.cover_url && !data.cover_remove ? project.cover_url : null);

    const pickCoverFromLibrary = (item: MediaItem) => {
        setData((prev) => ({
            ...prev,
            cover: null,
            cover_media_id: item.id,
            cover_remove: false,
        }));
        setCoverPreview(item.url);
        setCoverPicker(false);
    };

    const pickCoverFile = (file: File) => {
        setData((prev) => ({
            ...prev,
            cover: file,
            cover_media_id: null,
            cover_remove: false,
        }));
        setCoverPreview(null);
    };

    // A text cleared in the editor stays as `<p></p>`, which the server drops.
    const completeness = (locale: ContentLocale): number => {
        const filled = CONTENT_FIELDS.filter((f) =>
            f === 'body'
                ? hasRichText(data.body[locale] ?? '')
                : (data[f][locale] ?? '').trim() !== '',
        ).length;

        return Math.round((filled / CONTENT_FIELDS.length) * 100);
    };
    const compAll = {
        tg: completeness('tg'),
        ru: completeness('ru'),
        en: completeness('en'),
    };

    // A language version for the preview and the language checks: the site
    // shows it only with its title and a summary or text (PublicLocale).
    const versionOf = (locale: ContentLocale) => ({
        title: data.title[locale],
        summary: data.summary[locale],
        body: data.body[locale],
        hasText:
            (data.summary[locale] ?? '').trim() !== '' ||
            hasRichText(data.body[locale] ?? ''),
    });
    const versions = {
        tg: versionOf('tg'),
        ru: versionOf('ru'),
        en: versionOf('en'),
    };

    const setLocaleField = (
        field: 'title' | 'summary' | 'body',
        value: string,
    ) => setData(field, { ...data[field], [lang]: value });

    const handleCopyLocale = (from: ContentLocale, to: ContentLocale) => {
        setData((prev) => ({
            ...prev,
            title: { ...prev.title, [to]: prev.title[from] },
            summary: { ...prev.summary, [to]: prev.summary[from] },
            body: { ...prev.body, [to]: prev.body[from] },
            goals: { ...prev.goals, [to]: [...prev.goals[from]] },
        }));
    };

    // The server makes the address from the title when it's left empty.
    const titleForSlug =
        data.title[lang]?.trim() ||
        data.title.ru?.trim() ||
        data.title.tg?.trim() ||
        '';
    const suggestedSlug = slugify(titleForSlug) || 'adres-proekta';
    const generateSlug = () => {
        if (titleForSlug) {
            setData('slug', slugify(titleForSlug));
        }
    };

    // --- goals (per active language) ---
    const setGoals = (next: string[]) =>
        setData('goals', { ...data.goals, [lang]: next });
    const addGoal = () => setGoals([...data.goals[lang], '']);
    const updateGoal = (i: number, value: string) =>
        setGoals(data.goals[lang].map((g, idx) => (idx === i ? value : g)));
    const removeGoal = (i: number) =>
        setGoals(data.goals[lang].filter((_, idx) => idx !== i));

    // --- timeline (shared across languages) ---
    const addTimeline = () =>
        setData('timeline', [
            ...data.timeline,
            { date: '', text: '', tone: 'info' },
        ]);
    const updateTimeline = (
        i: number,
        key: keyof TimelineItem,
        value: string,
    ) =>
        setData(
            'timeline',
            data.timeline.map((t, idx) =>
                idx === i ? { ...t, [key]: value } : t,
            ),
        );
    const removeTimeline = (i: number) =>
        setData(
            'timeline',
            data.timeline.filter((_, idx) => idx !== i),
        );

    const setDirection = (key: keyof Direction, value: string) =>
        setData('direction', { ...data.direction, [key]: value });

    // Readiness: what the project page needs before it goes out.
    const hasTitle = data.title[lang].trim() !== '';
    const hasSummary = data.summary[lang].trim() !== '';
    const hasBody = hasRichText(data.body[lang]);
    const hasYears = data.years.trim() !== '';
    const hasCover = coverSrc !== null;
    const hasBilingual =
        data.title.tg.trim() !== '' && data.title.ru.trim() !== '';
    const readinessItems = [
        {
            id: 'title',
            label: `Название (${localeShort[lang]})`,
            done: hasTitle,
            weight: 25,
        },
        {
            id: 'summary',
            label: `Краткое описание (${localeShort[lang]})`,
            done: hasSummary,
            weight: 20,
        },
        {
            id: 'body',
            label: `Подробное описание (${localeShort[lang]})`,
            done: hasBody,
            weight: 20,
        },
        { id: 'years', label: 'Сроки проекта', done: hasYears, weight: 10 },
        { id: 'cover', label: 'Обложка', done: hasCover, weight: 10 },
        {
            id: 'bilingual',
            label: 'Заполнено на таджикском и русском',
            done: hasBilingual,
            weight: 15,
        },
    ];
    const readinessScore = readinessItems.reduce(
        (sum, item) => sum + (item.done ? item.weight : 0),
        0,
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
                      _editorial_version: project!.updated_at,
                  }
                : {}),
        }));

        form.post(isEdit ? update.url(project!.id) : store.url(), {
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
            unpublishUrl={isEdit ? unpublish.url(project!.id) : undefined}
            pendingChange={pending_change}
            changesNeedApproval={changes_need_approval}
            canApprove={can('projects.approve')}
            variant="gutenberg"
            onCopyLocale={handleCopyLocale}
            title={isEdit ? 'Редактирование проекта' : 'Новый проект'}
            subtitle="Опишите проект, цели, ход реализации и контакты дирекции."
            backLabel="Проекты"
            backHref={index.url()}
            status={project?.status}
            language={{
                active: lang,
                onChange: setLang,
                completeness: compAll,
            }}
            errors={errors}
            isDirty={isDirty}
            processing={processing}
            canPublish={can('projects.publish')}
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
                contentType: 'projects',
                contentId: project?.id ?? null,
                baseVersion: project?.updated_at ?? null,
                data,
                onRecover: (recovered) =>
                    form.setData({ ...data, ...recovered }),
            }}
            preview={{
                locales: versions,
                imageUrl: coverSrc,
                signedUrl: project?.preview_url,
                checklist: [
                    ...languageChecks(compAll, versions),
                    {
                        label: 'Период проекта указан',
                        ok: data.years.trim() !== '',
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
                        <div className="wp-title-wrapper">
                            <textarea
                                ref={titleRef}
                                id={`project-title-${lang}`}
                                value={data.title[lang]}
                                onChange={(e) => {
                                    setLocaleField('title', e.target.value);
                                    fitHeight(e.target);
                                }}
                                placeholder={
                                    lang === 'ru'
                                        ? 'Название проекта, например: Модернизация системы оповещения'
                                        : lang === 'tg'
                                          ? 'Название на таджикском…'
                                          : 'Название на английском…'
                                }
                                className="wp-title-input"
                                rows={1}
                                maxLength={255}
                                aria-label="Название проекта"
                            />
                            {(fieldError('title') ??
                                fieldError(`title.${lang}`)) && (
                                <div className="wp-field-error">
                                    {fieldError('title') ??
                                        fieldError(`title.${lang}`)}
                                </div>
                            )}
                        </div>

                        <div className="wp-lead-wrapper">
                            <textarea
                                ref={summaryRef}
                                id={`project-summary-${lang}`}
                                value={data.summary[lang]}
                                onChange={(e) => {
                                    setLocaleField('summary', e.target.value);
                                    fitHeight(e.target);
                                }}
                                placeholder="Краткое описание: показывается в карточке проекта и как вступление…"
                                className="wp-lead-input"
                                rows={2}
                                maxLength={1000}
                                aria-label="Краткое описание проекта"
                            />
                            {(fieldError('summary') ??
                                fieldError(`summary.${lang}`)) && (
                                <div className="wp-field-error">
                                    {fieldError('summary') ??
                                        fieldError(`summary.${lang}`)}
                                </div>
                            )}
                        </div>

                        <div className="wp-body-wrapper">
                            <RichEditor
                                key={lang}
                                value={data.body[lang]}
                                onChange={(html) =>
                                    setLocaleField('body', html)
                                }
                                placeholder="Подробно опишите проект…"
                            />
                            {(fieldError('body') ??
                                fieldError(`body.${lang}`)) && (
                                <div className="wp-field-error">
                                    {fieldError('body') ??
                                        fieldError(`body.${lang}`)}
                                </div>
                            )}
                        </div>

                        <EditorCanvasBlock
                            title={`Цели и задачи (${localeShort[lang]})`}
                            hint="Список на выбранном языке: на сайте — отдельным блоком."
                            action={
                                <Button
                                    variant="secondary"
                                    size="sm"
                                    icon={<Plus size={14} strokeWidth={2} />}
                                    onClick={addGoal}
                                >
                                    Добавить цель
                                </Button>
                            }
                        >
                            {data.goals[lang].length === 0 ? (
                                <p className="wp-canvas-block-empty">
                                    Цели не добавлены.
                                </p>
                            ) : (
                                <div className="wp-canvas-block-rows">
                                    {data.goals[lang].map((goal, i) => (
                                        <div
                                            key={i}
                                            style={{
                                                display: 'flex',
                                                gap: 6,
                                                alignItems: 'flex-start',
                                            }}
                                        >
                                            <span
                                                className="ui-mono"
                                                style={{
                                                    width: 22,
                                                    paddingTop: 8,
                                                    fontSize: 12.5,
                                                    color: 'var(--color-neutral-600)',
                                                }}
                                            >
                                                {String(i + 1).padStart(2, '0')}
                                            </span>
                                            <Textarea
                                                aria-label={`Цель ${i + 1}`}
                                                value={goal}
                                                onChange={(e) =>
                                                    updateGoal(
                                                        i,
                                                        e.target.value,
                                                    )
                                                }
                                                style={{
                                                    minHeight: 40,
                                                    flex: 1,
                                                }}
                                                maxLength={1000}
                                            />
                                            <IconButton
                                                label="Удалить цель"
                                                variant="ghost"
                                                onClick={() => removeGoal(i)}
                                            >
                                                <X
                                                    size={15}
                                                    strokeWidth={1.5}
                                                />
                                            </IconButton>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </EditorCanvasBlock>

                        <EditorCanvasBlock
                            title="Ход реализации"
                            hint="Этапы проекта — общие для всех языков."
                            action={
                                <Button
                                    variant="secondary"
                                    size="sm"
                                    icon={<Plus size={14} strokeWidth={2} />}
                                    onClick={addTimeline}
                                >
                                    Добавить этап
                                </Button>
                            }
                        >
                            {data.timeline.length === 0 ? (
                                <p className="wp-canvas-block-empty">
                                    Этапы не добавлены.
                                </p>
                            ) : (
                                <div className="wp-canvas-block-rows">
                                    {data.timeline.map((item, i) => (
                                        <div
                                            key={i}
                                            className="cms-stack-narrow"
                                            style={{
                                                display: 'grid',
                                                gridTemplateColumns:
                                                    '150px 1fr 130px 34px',
                                                gap: 6,
                                                alignItems: 'start',
                                            }}
                                        >
                                            <Input
                                                aria-label={`Этап ${i + 1}: когда`}
                                                value={item.date}
                                                onChange={(e) =>
                                                    updateTimeline(
                                                        i,
                                                        'date',
                                                        e.target.value,
                                                    )
                                                }
                                                placeholder="Июнь 2026"
                                                maxLength={100}
                                            />
                                            <Textarea
                                                aria-label={`Этап ${i + 1}: что сделано или запланировано`}
                                                value={item.text}
                                                onChange={(e) =>
                                                    updateTimeline(
                                                        i,
                                                        'text',
                                                        e.target.value,
                                                    )
                                                }
                                                placeholder="Что сделано / запланировано"
                                                style={{ minHeight: 38 }}
                                                maxLength={1000}
                                            />
                                            <Select
                                                aria-label={`Этап ${i + 1}: состояние`}
                                                value={item.tone}
                                                options={TONE_OPTIONS}
                                                onChange={(e) =>
                                                    updateTimeline(
                                                        i,
                                                        'tone',
                                                        e.target.value,
                                                    )
                                                }
                                            />
                                            <IconButton
                                                label="Удалить этап"
                                                variant="ghost"
                                                onClick={() =>
                                                    removeTimeline(i)
                                                }
                                            >
                                                <X
                                                    size={15}
                                                    strokeWidth={1.5}
                                                />
                                            </IconButton>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </EditorCanvasBlock>
                    </div>
                </main>

                {/* --------------------------------- Inspector Sidebar */}
                {sidebarOpen && (
                    <EditorInspector
                        label="Настройки проекта"
                        title={
                            <>
                                <FolderKanban size={15} />
                                <span>Проект</span>
                            </>
                        }
                        onClose={() => setSidebarOpen(false)}
                    >
                        <div className="wp-inspector-section">
                            <ReadinessWidget
                                title="Готовность проекта"
                                score={readinessScore}
                                items={readinessItems}
                            />
                        </div>

                        <InspectorSection title="Публикация">
                            <SlugField
                                id="project-slug"
                                value={data.slug}
                                onChange={(value) => setData('slug', value)}
                                onGenerate={generateSlug}
                                prefix={displayUrl(
                                    siteUrl(publicSiteUrl, '/projects/', lang),
                                )}
                                placeholder={suggestedSlug}
                                error={fieldError('slug')}
                            />
                        </InspectorSection>

                        <InspectorSection title="Параметры проекта">
                            <Field
                                label="Статус проекта"
                                required
                                error={fieldError('lifecycle_status')}
                            >
                                <Select
                                    value={data.lifecycle_status}
                                    options={reference.lifecycles}
                                    onChange={(e) =>
                                        setData(
                                            'lifecycle_status',
                                            e.target.value,
                                        )
                                    }
                                />
                            </Field>
                            <Field
                                label="Код проекта"
                                error={fieldError('code')}
                            >
                                <Input
                                    value={data.code}
                                    onChange={(e) =>
                                        setData('code', e.target.value)
                                    }
                                    placeholder="Проект 01"
                                    maxLength={100}
                                />
                            </Field>
                            <Field label="Сроки" error={fieldError('years')}>
                                <Input
                                    value={data.years}
                                    onChange={(e) =>
                                        setData('years', e.target.value)
                                    }
                                    placeholder="2026–2030"
                                    maxLength={100}
                                />
                            </Field>
                            <Field
                                label="Заказчик"
                                error={fieldError('customer')}
                            >
                                <Input
                                    value={data.customer}
                                    onChange={(e) =>
                                        setData('customer', e.target.value)
                                    }
                                    maxLength={255}
                                />
                            </Field>
                            <Field
                                label="Партнёры"
                                error={fieldError('partner')}
                            >
                                <Input
                                    value={data.partner}
                                    onChange={(e) =>
                                        setData('partner', e.target.value)
                                    }
                                    maxLength={255}
                                />
                            </Field>
                            <Field label="Бюджет" error={fieldError('budget')}>
                                <Input
                                    value={data.budget}
                                    onChange={(e) =>
                                        setData('budget', e.target.value)
                                    }
                                    placeholder="18,4 млн долл. США"
                                    maxLength={255}
                                />
                            </Field>
                        </InspectorSection>

                        <InspectorSection title="Дирекция проекта">
                            <Field
                                label="Адрес"
                                error={fieldError('direction.address')}
                            >
                                <Input
                                    value={data.direction.address}
                                    onChange={(e) =>
                                        setDirection('address', e.target.value)
                                    }
                                    maxLength={255}
                                />
                            </Field>
                            <Field
                                label="Телефон"
                                error={fieldError('direction.phone')}
                            >
                                <Input
                                    value={data.direction.phone}
                                    onChange={(e) =>
                                        setDirection('phone', e.target.value)
                                    }
                                    maxLength={100}
                                />
                            </Field>
                            <Field
                                label="Эл. почта"
                                error={fieldError('direction.email')}
                            >
                                <Input
                                    value={data.direction.email}
                                    onChange={(e) =>
                                        setDirection('email', e.target.value)
                                    }
                                    type="email"
                                    maxLength={255}
                                />
                            </Field>
                        </InspectorSection>

                        <InspectorSection title="Обложка">
                            <CoverField
                                src={coverSrc}
                                removable={!!project?.cover_url}
                                removed={data.cover_remove}
                                onRemovedChange={(removed) =>
                                    setData('cover_remove', removed)
                                }
                                onFile={pickCoverFile}
                                onOpenLibrary={() => setCoverPicker(true)}
                                error={
                                    fieldError('cover') ??
                                    fieldError('cover_media_id')
                                }
                            />
                        </InspectorSection>
                    </EditorInspector>
                )}
            </div>

            <MediaPicker
                open={coverPicker}
                onClose={() => setCoverPicker(false)}
                onSelect={pickCoverFromLibrary}
            />
        </EditorialFormShell>
    );
}
