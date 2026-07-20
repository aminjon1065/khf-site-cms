import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, ChevronDown, Plus, Save, Send, X } from 'lucide-react';
import { useState } from 'react';
import { useCan } from '@/lib/auth';
import type { ContentLocale, ContentStatus } from '@/lib/domain';
import { StatusBadge } from '@/ui/Badge';
import { Blueprint } from '@/ui/Blueprint';
import { Button, IconButton, LinkButton } from '@/ui/Button';
import { Checkbox, Field, Input, Select, Textarea } from '@/ui/Field';
import { LanguageTabs } from '@/ui/Nav';
import { Dropdown } from '@/ui/Overlay';
import { PageHeader } from '@/ui/PageHeader';

type LocaleMap = { ru: string; tg: string; en: string };
type GoalMap = { ru: string[]; tg: string[]; en: string[] };
type TimelineItem = { date: string; text: string; tone: string };
type Direction = { address: string; phone: string; email: string };
type PublishMode = 'now' | 'schedule' | 'review';

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
}

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
const TONE_OPTIONS: Option[] = [
    { value: 'success', label: 'Выполнено' },
    { value: 'info', label: 'В плане' },
    { value: 'warning', label: 'Внимание' },
    { value: 'neutral', label: 'Обычный' },
];

function emptyGoals(): GoalMap {
    return { ru: [], tg: [], en: [] };
}

export default function ProjectForm({ project, reference }: Props) {
    const can = useCan();
    const isEdit = !!project;
    const [lang, setLang] = useState<ContentLocale>('ru');

    const form = useForm({
        title: { ...EMPTY, ...project?.title } as LocaleMap,
        summary: { ...EMPTY, ...project?.summary } as LocaleMap,
        body: { ...EMPTY, ...project?.body } as LocaleMap,
        slug: project?.slug ?? '',
        lifecycle_status: (project?.lifecycle_status ?? 'preparation') as string,
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
        cover_remove: false,
        publish_mode: 'review' as PublishMode,
        action: 'draft' as 'draft' | 'submit',
    });
    const { data, setData, processing, errors } = form;

    const fieldError = (key: string): string | undefined =>
        (errors as Record<string, string | undefined>)[key];

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
    const updateTimeline = (i: number, key: keyof TimelineItem, value: string) =>
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

    const submit = (action: 'draft' | 'submit', mode?: PublishMode) => {
        form.transform((d) => ({
            ...d,
            action,
            publish_mode: mode ?? d.publish_mode,
            ...(isEdit ? { _method: 'put' } : {}),
        }));

        form.post(isEdit ? `/projects/${project!.id}` : '/projects', {
            forceFormData: true,
            preserveScroll: true,
        });
    };

    return (
        <>
            <Head
                title={isEdit ? 'Редактирование проекта' : 'Новый проект'}
            />

            <PageHeader
                eyebrow={
                    <Link
                        href="/projects"
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
                    style={{ display: 'flex', flexDirection: 'column', gap: 16 }}
                >
                    <Blueprint style={{ padding: 20 }}>
                        <div
                            style={{
                                display: 'flex',
                                justifyContent: 'space-between',
                                alignItems: 'center',
                                marginBottom: 14,
                                flexWrap: 'wrap',
                                gap: 10,
                            }}
                        >
                            <h3 className="ui-card-title" style={{ margin: 0 }}>
                                Описание
                            </h3>
                            <LanguageTabs
                                active={lang}
                                onChange={setLang}
                                completeness={compAll}
                            />
                        </div>

                        <Field
                            label="Название проекта"
                            required={lang === 'ru'}
                            error={
                                lang === 'ru'
                                    ? fieldError('title.ru')
                                    : undefined
                            }
                        >
                            <Input
                                value={data.title[lang]}
                                onChange={(e) =>
                                    setLocaleField('title', e.target.value)
                                }
                                hasError={lang === 'ru' && !!fieldError('title.ru')}
                                placeholder={
                                    lang === 'ru'
                                        ? 'Например: Модернизация системы оповещения'
                                        : 'Перевод названия'
                                }
                                maxLength={255}
                            />
                        </Field>

                        <Field
                            label="Краткое описание"
                            hint="Показывается в карточке проекта и как вступление."
                        >
                            <Textarea
                                value={data.summary[lang]}
                                onChange={(e) =>
                                    setLocaleField('summary', e.target.value)
                                }
                                style={{ minHeight: 72 }}
                                maxLength={1000}
                            />
                        </Field>

                        <Field label="Подробное описание">
                            <Textarea
                                value={data.body[lang]}
                                onChange={(e) =>
                                    setLocaleField('body', e.target.value)
                                }
                                style={{ minHeight: 160 }}
                            />
                        </Field>
                    </Blueprint>

                    {/* ---------------------------------------- goals */}
                    <Blueprint style={{ padding: 20 }}>
                        <div
                            style={{
                                display: 'flex',
                                justifyContent: 'space-between',
                                alignItems: 'center',
                                marginBottom: 10,
                            }}
                        >
                            <h3 className="ui-card-title" style={{ margin: 0 }}>
                                Цели и задачи ({lang.toUpperCase()})
                            </h3>
                            <Button
                                variant="ghost"
                                size="sm"
                                icon={<Plus size={14} strokeWidth={2} />}
                                onClick={addGoal}
                            >
                                Цель
                            </Button>
                        </div>

                        {data.goals[lang].length === 0 ? (
                            <p
                                style={{
                                    margin: 0,
                                    fontSize: 12.5,
                                    color: 'var(--color-neutral-400)',
                                }}
                            >
                                Цели не добавлены.
                            </p>
                        ) : (
                            <div
                                style={{
                                    display: 'flex',
                                    flexDirection: 'column',
                                    gap: 6,
                                }}
                            >
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
                                                color: 'var(--color-neutral-500)',
                                            }}
                                        >
                                            {String(i + 1).padStart(2, '0')}
                                        </span>
                                        <Textarea
                                            value={goal}
                                            onChange={(e) =>
                                                updateGoal(i, e.target.value)
                                            }
                                            style={{ minHeight: 40, flex: 1 }}
                                            maxLength={1000}
                                        />
                                        <IconButton
                                            label="Удалить цель"
                                            variant="ghost"
                                            onClick={() => removeGoal(i)}
                                        >
                                            <X size={15} strokeWidth={1.5} />
                                        </IconButton>
                                    </div>
                                ))}
                            </div>
                        )}
                    </Blueprint>

                    {/* ---------------------------------------- timeline */}
                    <Blueprint style={{ padding: 20 }}>
                        <div
                            style={{
                                display: 'flex',
                                justifyContent: 'space-between',
                                alignItems: 'center',
                                marginBottom: 4,
                            }}
                        >
                            <h3 className="ui-card-title" style={{ margin: 0 }}>
                                Ход реализации
                            </h3>
                            <Button
                                variant="ghost"
                                size="sm"
                                icon={<Plus size={14} strokeWidth={2} />}
                                onClick={addTimeline}
                            >
                                Этап
                            </Button>
                        </div>
                        <p
                            style={{
                                margin: '0 0 12px',
                                fontSize: 12.5,
                                color: 'var(--color-neutral-600)',
                            }}
                        >
                            Общая хронология проекта (единая для всех языков).
                        </p>

                        {data.timeline.length === 0 ? (
                            <p
                                style={{
                                    margin: 0,
                                    fontSize: 12.5,
                                    color: 'var(--color-neutral-400)',
                                }}
                            >
                                Этапы не добавлены.
                            </p>
                        ) : (
                            <div
                                style={{
                                    display: 'flex',
                                    flexDirection: 'column',
                                    gap: 10,
                                }}
                            >
                                {data.timeline.map((item, i) => (
                                    <div
                                        key={i}
                                        style={{
                                            display: 'grid',
                                            gridTemplateColumns:
                                                '150px 1fr 130px 34px',
                                            gap: 6,
                                            alignItems: 'start',
                                        }}
                                    >
                                        <Input
                                            value={item.date}
                                            onChange={(e) =>
                                                updateTimeline(
                                                    i,
                                                    'date',
                                                    e.target.value,
                                                )
                                            }
                                            placeholder="Июнь 2026"
                                        />
                                        <Textarea
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
                                        />
                                        <Select
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
                                            onClick={() => removeTimeline(i)}
                                        >
                                            <X size={15} strokeWidth={1.5} />
                                        </IconButton>
                                    </div>
                                ))}
                            </div>
                        )}
                    </Blueprint>
                </div>

                {/* --------------------------------------------- sidebar */}
                <div
                    style={{ display: 'flex', flexDirection: 'column', gap: 16 }}
                >
                    <Blueprint style={{ padding: 20 }}>
                        <h3
                            className="ui-card-title"
                            style={{ marginTop: 0, marginBottom: 14 }}
                        >
                            Параметры
                        </h3>

                        <Field
                            label="Статус проекта"
                            required
                            error={fieldError('lifecycle_status')}
                        >
                            <Select
                                value={data.lifecycle_status}
                                options={reference.lifecycles}
                                onChange={(e) =>
                                    setData('lifecycle_status', e.target.value)
                                }
                            />
                        </Field>
                        <Field label="Код проекта">
                            <Input
                                value={data.code}
                                onChange={(e) => setData('code', e.target.value)}
                                placeholder="Проект 01"
                            />
                        </Field>
                        <Field label="Сроки">
                            <Input
                                value={data.years}
                                onChange={(e) =>
                                    setData('years', e.target.value)
                                }
                                placeholder="2026–2030"
                            />
                        </Field>
                        <Field label="Заказчик">
                            <Input
                                value={data.customer}
                                onChange={(e) =>
                                    setData('customer', e.target.value)
                                }
                            />
                        </Field>
                        <Field label="Партнёры">
                            <Input
                                value={data.partner}
                                onChange={(e) =>
                                    setData('partner', e.target.value)
                                }
                            />
                        </Field>
                        <Field label="Бюджет">
                            <Input
                                value={data.budget}
                                onChange={(e) =>
                                    setData('budget', e.target.value)
                                }
                                placeholder="18,4 млн долл. США"
                            />
                        </Field>
                        <Field
                            label="Адрес (slug)"
                            hint="Пусто — из названия."
                            error={fieldError('slug')}
                        >
                            <Input
                                value={data.slug}
                                onChange={(e) => setData('slug', e.target.value)}
                                hasError={!!fieldError('slug')}
                                placeholder="early-warning-system"
                            />
                        </Field>
                    </Blueprint>

                    <Blueprint style={{ padding: 20 }}>
                        <h3
                            className="ui-card-title"
                            style={{ marginTop: 0, marginBottom: 14 }}
                        >
                            Дирекция проекта
                        </h3>
                        <Field label="Адрес">
                            <Input
                                value={data.direction.address}
                                onChange={(e) =>
                                    setDirection('address', e.target.value)
                                }
                            />
                        </Field>
                        <Field label="Телефон">
                            <Input
                                value={data.direction.phone}
                                onChange={(e) =>
                                    setDirection('phone', e.target.value)
                                }
                            />
                        </Field>
                        <Field label="E-mail">
                            <Input
                                value={data.direction.email}
                                onChange={(e) =>
                                    setDirection('email', e.target.value)
                                }
                            />
                        </Field>
                    </Blueprint>

                    <Blueprint style={{ padding: 20 }}>
                        <h3
                            className="ui-card-title"
                            style={{ marginTop: 0, marginBottom: 14 }}
                        >
                            Обложка
                        </h3>
                        {project?.cover_url && !data.cover_remove && (
                            <img
                                src={project.cover_url}
                                alt=""
                                style={{
                                    width: '100%',
                                    borderRadius: 6,
                                    marginBottom: 10,
                                    border: '1px solid var(--color-divider)',
                                }}
                            />
                        )}
                        <input
                            type="file"
                            accept="image/png,image/jpeg,image/webp"
                            onChange={(e) =>
                                setData('cover', e.target.files?.[0] ?? null)
                            }
                            style={{ fontSize: 13 }}
                        />
                        {project?.cover_url && (
                            <Checkbox
                                className="mt-2"
                                label="Удалить текущую обложку"
                                checked={data.cover_remove}
                                onChange={(e) =>
                                    setData('cover_remove', e.target.checked)
                                }
                            />
                        )}
                    </Blueprint>
                </div>
            </div>

            {/* --------------------------------------------- sticky actions */}
            <div className="news-form-actions">
                <LinkButton href="/projects" variant="ghost">
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
