import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, ChevronDown, Save, Send } from 'lucide-react';
import { useRef, useState } from 'react';
import ProjectController from '@/actions/App/Http/Controllers/Cms/ProjectController';
import { useSaveShortcut } from '@/hooks/use-save-shortcut';
import { useCan } from '@/lib/auth';
import type { ContentLocale } from '@/lib/domain';
import { StatusBadge } from '@/ui/Badge';
import { Button, LinkButton } from '@/ui/Button';
import type { MediaItem } from '@/ui/MediaPicker';
import { Dropdown } from '@/ui/Overlay';
import { PageHeader } from '@/ui/PageHeader';
import { CoverCard } from './form/CoverCard';
import { DescriptionCard } from './form/DescriptionCard';
import { DirectionCard } from './form/DirectionCard';
import { GoalsCard } from './form/GoalsCard';
import { ParametersCard } from './form/ParametersCard';
import { TimelineCard } from './form/TimelineCard';
import type {
    Direction,
    GoalMap,
    LocaleMap,
    Option,
    ProjectData,
    PublishMode,
    TimelineItem,
} from './form/types';

interface Props {
    project: ProjectData | null;
    reference: {
        lifecycles: Option[];
        authors: Option[];
    };
}

const EMPTY: LocaleMap = { ru: '', tg: '', en: '' };
const CONTENT_FIELDS: ('title' | 'summary' | 'body')[] = [
    'title',
    'summary',
    'body',
];

function emptyGoals(): GoalMap {
    return { ru: [], tg: [], en: [] };
}

export default function ProjectForm({ project, reference }: Props) {
    const can = useCan();
    const isEdit = !!project;
    const [lang, setLang] = useState<ContentLocale>('ru');
    const [coverPicker, setCoverPicker] = useState(false);
    const [coverPreview, setCoverPreview] = useState<string | null>(null);
    const coverFileRef = useRef<HTMLInputElement>(null);

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
    const { data, setData, processing, errors } = form;

    const fieldError = (key: string): string | undefined =>
        (errors as Record<string, string | undefined>)[key];

    const pickCoverFromLibrary = (item: MediaItem) => {
        setData('cover', null);
        setData('cover_media_id', item.id);
        setData('cover_remove', false);
        setCoverPreview(item.url);
        setCoverPicker(false);
    };

    const onCoverFileChange = (file: File | null) => {
        if (file) {
            setData('cover', file);
            setData('cover_media_id', null);
            setData('cover_remove', false);
            setCoverPreview(URL.createObjectURL(file));
        }
    };

    // Превью: свежий выбор (файл/медиатека) приоритетнее существующей обложки.
    const coverSrc =
        coverPreview ??
        (project?.cover_url && !data.cover_remove ? project.cover_url : null);

    const completeness = (locale: ContentLocale): number => {
        const filled = CONTENT_FIELDS.filter(
            (f) => (data[f][locale] ?? '').trim() !== '',
        ).length;

        return Math.round((filled / CONTENT_FIELDS.length) * 100);
    };
    const compAll = {
        tg: completeness('tg'),
        ru: completeness('ru'),
        en: completeness('en'),
    };

    const setLocaleField = (
        field: 'title' | 'summary' | 'body',
        value: string,
    ) => setData(field, { ...data[field], [lang]: value });

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
            ...(isEdit ? { _method: 'put' } : {}),
        }));

        form.post(
            isEdit
                ? ProjectController.update.url(project!.id)
                : ProjectController.store.url(),
            {
                forceFormData: true,
                preserveScroll: true,
                preserveState: stay,
            },
        );
    };

    // Ctrl/Cmd+S — сохранить черновик и остаться в редакторе (stay = true).
    useSaveShortcut(() => submit('draft', undefined, true), !processing);

    return (
        <>
            <Head title={isEdit ? 'Редактирование проекта' : 'Новый проект'} />

            <PageHeader
                eyebrow={
                    <Link
                        href={ProjectController.index.url()}
                        style={{
                            display: 'inline-flex',
                            alignItems: 'center',
                            gap: 6,
                            color: 'var(--color-neutral-600)',
                            textDecoration: 'none',
                        }}
                    >
                        <ArrowLeft size={14} strokeWidth={1.75} /> Проекты
                    </Link>
                }
                title={isEdit ? 'Редактирование проекта' : 'Новый проект'}
                subtitle="Опишите проект, цели, ход реализации и контакты дирекции."
                actions={
                    isEdit && project ? (
                        <StatusBadge status={project.status} />
                    ) : null
                }
            />

            <div
                className="cms-two-col"
                style={{
                    display: 'grid',
                    gridTemplateColumns: '1.7fr 1fr',
                    gap: 16,
                    alignItems: 'start',
                }}
            >
                {/* ------------------------------------------------ main */}
                <div
                    style={{
                        display: 'flex',
                        flexDirection: 'column',
                        gap: 16,
                    }}
                >
                    <DescriptionCard
                        data={data}
                        lang={lang}
                        setLang={setLang}
                        compAll={compAll}
                        fieldError={fieldError}
                        setLocaleField={setLocaleField}
                    />
                    <GoalsCard
                        lang={lang}
                        goals={data.goals[lang]}
                        addGoal={addGoal}
                        updateGoal={updateGoal}
                        removeGoal={removeGoal}
                    />
                    <TimelineCard
                        timeline={data.timeline}
                        addTimeline={addTimeline}
                        updateTimeline={updateTimeline}
                        removeTimeline={removeTimeline}
                    />
                </div>

                {/* --------------------------------------------- sidebar */}
                <div
                    style={{
                        display: 'flex',
                        flexDirection: 'column',
                        gap: 16,
                    }}
                >
                    <ParametersCard
                        data={data}
                        setData={setData}
                        fieldError={fieldError}
                        lifecycles={reference.lifecycles}
                    />
                    <DirectionCard
                        direction={data.direction}
                        setDirection={setDirection}
                    />
                    <CoverCard
                        coverSrc={coverSrc}
                        coverFileRef={coverFileRef}
                        onFileChange={onCoverFileChange}
                        onUploadClick={() => coverFileRef.current?.click()}
                        coverPicker={coverPicker}
                        setCoverPicker={setCoverPicker}
                        pickCoverFromLibrary={pickCoverFromLibrary}
                        hasExistingCover={!!project?.cover_url}
                        coverRemove={data.cover_remove}
                        setCoverRemove={(remove) =>
                            setData('cover_remove', remove)
                        }
                    />
                </div>
            </div>

            {/* --------------------------------------------- sticky actions */}
            <div className="news-form-actions">
                <LinkButton
                    href={ProjectController.index.url()}
                    variant="ghost"
                >
                    Отмена
                </LinkButton>
                <div style={{ flex: 1 }} />
                <Button
                    variant="secondary"
                    icon={<Save size={15} strokeWidth={1.75} />}
                    loading={processing}
                    onClick={() => submit('draft')}
                >
                    Сохранить черновик
                </Button>
                {can('projects.publish') ? (
                    <Dropdown
                        align="right"
                        trigger={({ toggle }) => (
                            <Button
                                variant="primary"
                                iconRight={
                                    <ChevronDown size={15} strokeWidth={2} />
                                }
                                onClick={toggle}
                            >
                                Опубликовать
                            </Button>
                        )}
                        items={[
                            {
                                label: 'Опубликовать сейчас',
                                onSelect: () => submit('submit', 'now'),
                            },
                            { separator: true },
                            {
                                label: 'Отправить на согласование',
                                onSelect: () => submit('submit', 'review'),
                            },
                        ]}
                    />
                ) : (
                    <Button
                        variant="primary"
                        icon={<Send size={15} strokeWidth={1.75} />}
                        loading={processing}
                        onClick={() => submit('submit', 'review')}
                    >
                        На согласование
                    </Button>
                )}
            </div>
        </>
    );
}
