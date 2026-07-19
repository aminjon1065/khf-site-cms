import { Head, router, useForm } from '@inertiajs/react';
import { ArrowLeft, CheckCircle2, Copy, TriangleAlert, X } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import type { ContentLocale } from '@/lib/domain';
import type { Severity } from '@/lib/domain';
import { useT } from '@/lib/i18n';
import { SeverityBadge, Tag } from '@/ui/Badge';
import { Blueprint } from '@/ui/Blueprint';
import { Button, IconButton } from '@/ui/Button';
import {
    Checkbox,
    DatePicker,
    Field,
    Input,
    Radio,
    RadioCard,
    Segmented,
    Select,
    Textarea,
} from '@/ui/Field';
import { LanguageTabs, Stepper } from '@/ui/Nav';

type Localized = Record<string, string>;

interface Reference {
    severities: { value: string; label: string }[];
    hazards: { value: string; label: string; icon: string }[];
    channels: { value: string; label: string }[];
    regions: {
        id: number;
        code: string;
        name: string;
        districts_count: number;
        districts: { id: number; name: string }[];
    }[];
    sources: string[];
    riskCategories: { value: string; label: string }[];
    approvers: { id: number; name: string }[];
    instructions: { id: number; name: string }[];
}

interface WizardAlert {
    id: number;
    internal_title: string;
    hazard_type: string;
    severity: string;
    source: string | null;
    risk_category: string | null;
    territory_type: string;
    territory_note: string | null;
    starts_at: string | null;
    ends_at: string | null;
    scheduled_at: string | null;
    channels: string[];
    approver_id: number | null;
    title: Localized;
    summary: Localized;
    body: Localized;
    instructions: Localized;
    contacts: Localized;
    regions: number[];
    districts: number[];
    related_instructions: number[];
}

interface Props {
    alert: WizardAlert | null;
    reference: Reference;
}

const STEPS = [
    { label: 'Основная информация' },
    { label: 'Территория' },
    { label: 'Содержание' },
    { label: 'Дополнительно' },
    { label: 'Публикация' },
];

const CONTENT_FIELDS = ['title', 'summary', 'body', 'instructions'] as const;
const LEVEL_DESC: Record<string, string> = {
    info: 'Справочное сообщение',
    attention: 'Возможно ухудшение',
    warning: 'Вероятная угроза',
    danger: 'Реальная угроза',
    critical: 'Угроза жизни',
};

export default function AlertWizard({ alert, reference }: Props) {
    const { t } = useT();
    const [step, setStep] = useState(0);
    const [lang, setLang] = useState<ContentLocale>('ru');
    const [publishMode, setPublishMode] = useState<
        'now' | 'schedule' | 'review'
    >('review');
    const [preview, setPreview] = useState<'desktop' | 'mobile'>('desktop');
    const [savedAt, setSavedAt] = useState<string | null>(null);
    const emptyLoc = { ru: '', tg: '', en: '' };

    const form = useForm({
        internal_title: alert?.internal_title ?? '',
        hazard_type: alert?.hazard_type ?? '',
        severity: alert?.severity ?? 'warning',
        source: alert?.source ?? '',
        risk_category: alert?.risk_category ?? '',
        territory_type: alert?.territory_type ?? 'regions',
        territory_note: alert?.territory_note ?? '',
        starts_at: alert?.starts_at ?? '',
        ends_at: alert?.ends_at ?? '',
        scheduled_at: alert?.scheduled_at ?? '',
        channels: alert?.channels ?? ['site', 'sos_app'],
        approver_id: alert?.approver_id ?? ('' as number | ''),
        title: { ...emptyLoc, ...alert?.title },
        summary: { ...emptyLoc, ...alert?.summary },
        body: { ...emptyLoc, ...alert?.body },
        instructions: { ...emptyLoc, ...alert?.instructions },
        contacts: { ...emptyLoc, ...alert?.contacts },
        regions: alert?.regions ?? [],
        districts: alert?.districts ?? [],
        related_instructions: alert?.related_instructions ?? [],
        publish_mode: 'review',
        action: 'draft',
    });
    const { data, setData, processing, errors } = form;

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

    // Autosave draft every 30s in edit mode.
    const dirty = useRef(false);
    useEffect(() => {
        dirty.current = true;
    }, [data]);
    useEffect(() => {
        if (!alert) {
            return;
        }

        const timer = setInterval(() => {
            if (!dirty.current) {
                return;
            }

            dirty.current = false;
            router.put(
                `/alerts/${alert.id}`,
                { ...data, action: 'draft' },
                {
                    preserveScroll: true,
                    preserveState: true,
                    only: [],
                    onSuccess: () =>
                        setSavedAt(
                            new Date().toLocaleTimeString('ru-RU', {
                                hour: '2-digit',
                                minute: '2-digit',
                            }),
                        ),
                },
            );
        }, 30000);

        return () => clearInterval(timer);
    }, [alert, data]);

    const submit = (action: 'draft' | 'submit') => {
        const payload = { ...data, action, publish_mode: publishMode };
        const opts = {
            onSuccess: () =>
                setSavedAt(
                    new Date().toLocaleTimeString('ru-RU', {
                        hour: '2-digit',
                        minute: '2-digit',
                    }),
                ),
        };

        if (alert) {
            router.put(`/alerts/${alert.id}`, payload, opts);
        } else {
            router.post('/alerts', payload, opts);
        }
    };

    const toggleRegion = (id: number) => {
        setData(
            'regions',
            data.regions.includes(id)
                ? data.regions.filter((r) => r !== id)
                : [...data.regions, id],
        );
    };
    const toggleDistrict = (id: number) => {
        setData(
            'districts',
            data.districts.includes(id)
                ? data.districts.filter((d) => d !== id)
                : [...data.districts, id],
        );
    };
    const toggleChannel = (v: string) => {
        setData(
            'channels',
            data.channels.includes(v)
                ? data.channels.filter((c) => c !== v)
                : [...data.channels, v],
        );
    };
    const copyFromRu = () => {
        CONTENT_FIELDS.forEach((f) =>
            setData(f, { ...data[f], [lang]: data[f].ru }),
        );
        setData('contacts', { ...data.contacts, [lang]: data.contacts.ru });
    };

    const checklist = [
        { label: 'Выбран уровень опасности', ok: !!data.severity },
        {
            label: 'Указаны регионы',
            ok: data.territory_type === 'country' || data.regions.length > 0,
        },
        { label: 'Заполнен таджикский текст', ok: compAll.tg === 100 },
        { label: 'Заполнен русский текст', ok: compAll.ru === 100 },
        { label: 'Перевод на английский', ok: compAll.en === 100 },
        {
            label: 'Добавлена инструкция населению',
            ok: (data.instructions.ru ?? '').trim() !== '',
        },
        { label: 'Установлен срок действия', ok: !!data.ends_at },
        { label: 'Указано ответственное лицо', ok: !!data.approver_id },
    ];

    return (
        <>
            <Head
                title={
                    alert
                        ? 'Редактирование предупреждения'
                        : 'Новое предупреждение'
                }
            />

            <div
                style={{
                    display: 'flex',
                    alignItems: 'flex-start',
                    gap: 12,
                    marginBottom: 18,
                }}
            >
                <IconButton
                    label={t('action.back')}
                    variant="secondary"
                    onClick={() => router.visit('/alerts')}
                >
                    <ArrowLeft size={17} strokeWidth={1.5} />
                </IconButton>
                <div style={{ flex: 1 }}>
                    <h1 className="ui-page-title">
                        {alert
                            ? 'Редактирование предупреждения'
                            : 'Новое предупреждение'}
                    </h1>
                    <div className="ui-page-sub">
                        Этап {step + 1} из 5 · {STEPS[step].label}
                    </div>
                </div>
                <Tag tone="neutral">
                    {savedAt
                        ? `Черновик · сохранено в ${savedAt}`
                        : alert
                          ? 'Черновик'
                          : 'Не сохранён'}
                </Tag>
            </div>

            <Blueprint style={{ padding: '16px 18px', marginBottom: 20 }}>
                <Stepper steps={STEPS} current={step} onStep={setStep} />
            </Blueprint>

            <div style={{ marginBottom: 90 }}>
                {step === 0 && (
                    <Step1 {...{ data, setData, errors, reference }} />
                )}
                {step === 1 && (
                    <Step2
                        {...{
                            data,
                            setData,
                            reference,
                            toggleRegion,
                            toggleDistrict,
                        }}
                    />
                )}
                {step === 2 && (
                    <Step3
                        {...{
                            data,
                            setData,
                            lang,
                            setLang,
                            compAll,
                            copyFromRu,
                            checklist,
                        }}
                    />
                )}
                {step === 3 && <Step4 {...{ data, setData, reference }} />}
                {step === 4 && (
                    <Step5
                        {...{
                            data,
                            setData,
                            reference,
                            publishMode,
                            setPublishMode,
                            preview,
                            setPreview,
                            toggleChannel,
                            checklist,
                        }}
                    />
                )}
            </div>

            {/* Sticky action bar */}
            <div
                className="ui-actionbar"
                style={{ marginLeft: -20, marginRight: -20, marginBottom: -24 }}
            >
                <Button
                    variant="secondary"
                    disabled={step === 0}
                    onClick={() => setStep((s) => Math.max(0, s - 1))}
                    icon={<ArrowLeft size={15} strokeWidth={1.5} />}
                >
                    {t('action.back')}
                </Button>
                <span
                    style={{ fontSize: 12, color: 'var(--color-neutral-500)' }}
                >
                    {alert
                        ? 'Черновик сохраняется автоматически'
                        : 'Сохраните черновик, чтобы включить автосохранение'}
                </span>
                <div style={{ display: 'flex', gap: 8 }}>
                    <Button
                        variant="secondary"
                        loading={processing}
                        onClick={() => submit('draft')}
                    >
                        {t('action.save_draft')}
                    </Button>
                    {step < 4 ? (
                        <Button
                            variant="primary"
                            onClick={() => setStep((s) => Math.min(4, s + 1))}
                        >
                            {t('action.next')}
                        </Button>
                    ) : (
                        <Button
                            variant="primary"
                            loading={processing}
                            onClick={() => submit('submit')}
                        >
                            {publishMode === 'now'
                                ? 'Опубликовать'
                                : publishMode === 'schedule'
                                  ? 'Запланировать'
                                  : 'Отправить на согласование'}
                        </Button>
                    )}
                </div>
            </div>
        </>
    );
}

/* ------------------------------------------------------------------ steps */

type StepProps = {
    data: ReturnType<typeof useForm>['data'] extends never ? never : any;
    setData: (key: string, value: unknown) => void;
    errors: Record<string, string>;
    reference: Reference;
};

function Step1({
    data,
    setData,
    errors,
    reference,
}: Pick<StepProps, 'data' | 'setData' | 'errors' | 'reference'>) {
    return (
        <div
            style={{
                maxWidth: 760,
                display: 'flex',
                flexDirection: 'column',
                gap: 18,
            }}
        >
            <div
                style={{
                    display: 'grid',
                    gridTemplateColumns: '1fr 1fr',
                    gap: 16,
                }}
            >
                <Field
                    label="Тип события"
                    required
                    error={errors.hazard_type}
                    hint="Определяет иконку и связанные инструкции"
                >
                    <Select
                        placeholder="Выберите тип"
                        value={data.hazard_type}
                        options={reference.hazards}
                        onChange={(e) => setData('hazard_type', e.target.value)}
                        hasError={!!errors.hazard_type}
                    />
                </Field>
                <Field label="Источник данных">
                    <Select
                        placeholder="Не указан"
                        value={data.source}
                        onChange={(e) => setData('source', e.target.value)}
                    >
                        {reference.sources.map((s) => (
                            <option key={s} value={s}>
                                {s}
                            </option>
                        ))}
                    </Select>
                </Field>
            </div>

            <Field label="Уровень опасности" required error={errors.severity}>
                <div
                    style={{
                        display: 'grid',
                        gridTemplateColumns: 'repeat(5, 1fr)',
                        gap: 8,
                    }}
                    className="cms-levels-grid"
                >
                    {reference.severities.map((s) => (
                        <RadioCard
                            key={s.value}
                            active={data.severity === s.value}
                            onSelect={() => setData('severity', s.value)}
                        >
                            <SeverityBadge severity={s.value as Severity} />
                            <span
                                style={{
                                    fontSize: 11.5,
                                    color: 'var(--color-neutral-600)',
                                    marginTop: 4,
                                }}
                            >
                                {LEVEL_DESC[s.value]}
                            </span>
                        </RadioCard>
                    ))}
                </div>
            </Field>

            <Field
                label="Внутреннее название"
                required
                error={errors.internal_title}
                hint="Видно только сотрудникам. Публичный заголовок задаётся на этапе «Содержание»."
            >
                <Input
                    value={data.internal_title}
                    onChange={(e) => setData('internal_title', e.target.value)}
                    placeholder="Селевая опасность — Хатлонская область, июль 2026"
                    hasError={!!errors.internal_title}
                />
            </Field>

            <div
                style={{
                    display: 'grid',
                    gridTemplateColumns: '1fr 1fr',
                    gap: 16,
                }}
            >
                <Field label="Начало действия" error={errors.starts_at}>
                    <DatePicker
                        withTime
                        value={data.starts_at}
                        onChange={(e) => setData('starts_at', e.target.value)}
                    />
                </Field>
                <Field label="Автоматическое завершение" error={errors.ends_at}>
                    <DatePicker
                        withTime
                        value={data.ends_at}
                        onChange={(e) => setData('ends_at', e.target.value)}
                        hasError={!!errors.ends_at}
                    />
                </Field>
            </div>
        </div>
    );
}

function Step2({
    data,
    setData,
    reference,
    toggleRegion,
    toggleDistrict,
}: {
    data: any;
    setData: (k: string, v: unknown) => void;
    reference: Reference;
    toggleRegion: (id: number) => void;
    toggleDistrict: (id: number) => void;
}) {
    const [expanded, setExpanded] = useState<number | null>(null);

    return (
        <div
            style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 20 }}
            className="cms-two-col"
        >
            <div style={{ display: 'flex', flexDirection: 'column', gap: 14 }}>
                <Field label="Затронутая территория" required>
                    <div style={{ display: 'flex', gap: 18 }}>
                        <Radio
                            name="territory"
                            label="Вся страна"
                            checked={data.territory_type === 'country'}
                            onChange={() =>
                                setData('territory_type', 'country')
                            }
                        />
                        <Radio
                            name="territory"
                            label="Отдельные регионы и районы"
                            checked={data.territory_type === 'regions'}
                            onChange={() =>
                                setData('territory_type', 'regions')
                            }
                        />
                    </div>
                </Field>

                {data.territory_type === 'regions' && (
                    <div
                        style={{
                            display: 'flex',
                            flexDirection: 'column',
                            gap: 6,
                        }}
                    >
                        {reference.regions.map((r) => {
                            const selectedDistricts = r.districts.filter((d) =>
                                data.districts.includes(d.id),
                            ).length;

                            return (
                                <Blueprint
                                    key={r.id}
                                    corners={false}
                                    style={{ padding: 10 }}
                                >
                                    <div
                                        style={{
                                            display: 'flex',
                                            alignItems: 'center',
                                            gap: 10,
                                        }}
                                    >
                                        <Checkbox
                                            checked={data.regions.includes(
                                                r.id,
                                            )}
                                            onChange={() => toggleRegion(r.id)}
                                        />
                                        <div style={{ flex: 1 }}>
                                            <div
                                                style={{
                                                    fontSize: 13.5,
                                                    fontWeight: 500,
                                                }}
                                            >
                                                {r.name}
                                            </div>
                                            <div
                                                style={{
                                                    fontSize: 11.5,
                                                    color: 'var(--color-neutral-500)',
                                                }}
                                            >
                                                {r.districts_count} районов
                                                {selectedDistricts > 0
                                                    ? ` · выбрано ${selectedDistricts}`
                                                    : ''}
                                            </div>
                                        </div>
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            onClick={() =>
                                                setExpanded(
                                                    expanded === r.id
                                                        ? null
                                                        : r.id,
                                                )
                                            }
                                        >
                                            Районы
                                        </Button>
                                    </div>
                                    {expanded === r.id && (
                                        <div
                                            style={{
                                                display: 'flex',
                                                flexWrap: 'wrap',
                                                gap: 10,
                                                marginTop: 8,
                                                paddingLeft: 26,
                                            }}
                                        >
                                            {r.districts.map((d) => (
                                                <Checkbox
                                                    key={d.id}
                                                    label={d.name}
                                                    checked={data.districts.includes(
                                                        d.id,
                                                    )}
                                                    onChange={() =>
                                                        toggleDistrict(d.id)
                                                    }
                                                />
                                            ))}
                                        </div>
                                    )}
                                </Blueprint>
                            );
                        })}
                    </div>
                )}

                <Field label="Текстовое описание территории">
                    <Textarea
                        value={data.territory_note}
                        onChange={(e) =>
                            setData('territory_note', e.target.value)
                        }
                        placeholder="Например: предгорные районы Хатлонской области — Дангара, Фархор, Восе…"
                    />
                </Field>
            </div>

            <div>
                <div className="ui-kicker" style={{ marginBottom: 8 }}>
                    Схема регионов · выбор кликом
                </div>
                <Blueprint
                    style={{
                        padding: 16,
                        display: 'grid',
                        gridTemplateColumns: '1fr 1fr',
                        gap: 10,
                    }}
                >
                    {reference.regions.map((r) => {
                        const active = data.regions.includes(r.id);

                        return (
                            <button
                                key={r.id}
                                type="button"
                                onClick={() => toggleRegion(r.id)}
                                style={{
                                    padding: 14,
                                    border: `1px solid ${active ? 'var(--sev-warning)' : 'var(--color-divider)'}`,
                                    background: active
                                        ? 'var(--sev-warning-soft)'
                                        : '#fff',
                                    cursor: 'pointer',
                                    textAlign: 'left',
                                    fontSize: 12.5,
                                }}
                            >
                                {r.name}
                            </button>
                        );
                    })}
                </Blueprint>
                <div
                    style={{
                        display: 'flex',
                        gap: 14,
                        marginTop: 10,
                        fontSize: 12,
                        color: 'var(--color-neutral-600)',
                    }}
                >
                    <span
                        style={{
                            display: 'inline-flex',
                            alignItems: 'center',
                            gap: 5,
                        }}
                    >
                        <span
                            style={{
                                width: 12,
                                height: 12,
                                background: '#fff',
                                border: '1px solid var(--color-divider)',
                            }}
                        />{' '}
                        штатно
                    </span>
                    <span
                        style={{
                            display: 'inline-flex',
                            alignItems: 'center',
                            gap: 5,
                        }}
                    >
                        <span
                            style={{
                                width: 12,
                                height: 12,
                                background: 'var(--sev-warning-soft)',
                                border: '1px solid var(--sev-warning)',
                            }}
                        />{' '}
                        в зоне предупреждения
                    </span>
                </div>
            </div>
        </div>
    );
}

function Step3({
    data,
    setData,
    lang,
    setLang,
    compAll,
    copyFromRu,
    checklist,
}: {
    data: any;
    setData: (k: string, v: unknown) => void;
    lang: ContentLocale;
    setLang: (l: ContentLocale) => void;
    compAll: Record<string, number>;
    copyFromRu: () => void;
    checklist: { label: string; ok: boolean }[];
}) {
    const setLoc = (field: string, value: string) =>
        setData(field, { ...data[field], [lang]: value });

    return (
        <div
            style={{
                display: 'grid',
                gridTemplateColumns: 'minmax(0,1fr) 300px',
                gap: 20,
            }}
            className="cms-two-col"
        >
            <div>
                <div
                    style={{
                        display: 'flex',
                        alignItems: 'center',
                        justifyContent: 'space-between',
                        marginBottom: 14,
                    }}
                >
                    <LanguageTabs
                        active={lang}
                        onChange={setLang}
                        completeness={compAll}
                    />
                    {lang !== 'ru' && (
                        <Button
                            variant="ghost"
                            size="sm"
                            icon={<Copy size={14} strokeWidth={1.5} />}
                            onClick={copyFromRu}
                        >
                            Скопировать из русского
                        </Button>
                    )}
                </div>
                <div
                    style={{
                        display: 'flex',
                        flexDirection: 'column',
                        gap: 14,
                    }}
                >
                    <Field label="Заголовок" required>
                        <Input
                            value={data.title[lang] ?? ''}
                            onChange={(e) => setLoc('title', e.target.value)}
                            placeholder="Публичный заголовок предупреждения"
                        />
                    </Field>
                    <Field
                        label="Краткое описание"
                        required
                        hint="Для карточки на главной и push-уведомлений"
                    >
                        <Textarea
                            value={data.summary[lang] ?? ''}
                            onChange={(e) => setLoc('summary', e.target.value)}
                            style={{ minHeight: 70 }}
                        />
                    </Field>
                    <Field label="Полное описание" required>
                        <Textarea
                            value={data.body[lang] ?? ''}
                            onChange={(e) => setLoc('body', e.target.value)}
                            style={{ minHeight: 120 }}
                        />
                    </Field>
                    <Field label="Инструкция населению" required>
                        <Textarea
                            value={data.instructions[lang] ?? ''}
                            onChange={(e) =>
                                setLoc('instructions', e.target.value)
                            }
                            style={{ minHeight: 90 }}
                        />
                    </Field>
                    <Field label="Контактная информация">
                        <Input
                            value={data.contacts[lang] ?? ''}
                            onChange={(e) => setLoc('contacts', e.target.value)}
                            placeholder="Единая служба спасения — 112"
                        />
                    </Field>
                </div>
            </div>
            <ChecklistPanel checklist={checklist} compAll={compAll} />
        </div>
    );
}

function Step4({
    data,
    setData,
    reference,
}: {
    data: any;
    setData: (k: string, v: unknown) => void;
    reference: Reference;
}) {
    const toggleInstruction = (id: number) => {
        setData(
            'related_instructions',
            data.related_instructions.includes(id)
                ? data.related_instructions.filter((x: number) => x !== id)
                : [...data.related_instructions, id],
        );
    };

    return (
        <div
            style={{
                maxWidth: 760,
                display: 'flex',
                flexDirection: 'column',
                gap: 18,
            }}
        >
            <div
                style={{
                    display: 'grid',
                    gridTemplateColumns: '1fr 1fr',
                    gap: 16,
                }}
            >
                <Field label="Изображение">
                    <div className="ui-dropzone">
                        Перетащите файл или выберите из медиабиблиотеки
                    </div>
                </Field>
                <Field label="Документы">
                    <div className="ui-dropzone">
                        Прикрепите прогноз или официальный документ (PDF)
                    </div>
                </Field>
            </div>
            <Field label="Связанные инструкции населению">
                <div style={{ display: 'flex', flexWrap: 'wrap', gap: 8 }}>
                    {reference.instructions.map((i) => {
                        const active = data.related_instructions.includes(i.id);

                        return (
                            <button
                                key={i.id}
                                type="button"
                                onClick={() => toggleInstruction(i.id)}
                                style={{
                                    border: 0,
                                    background: 'transparent',
                                    cursor: 'pointer',
                                    padding: 0,
                                }}
                            >
                                <Tag tone={active ? 'accent' : 'outline'}>
                                    {i.name}
                                    {active && (
                                        <X
                                            size={12}
                                            strokeWidth={2}
                                            style={{ marginLeft: 4 }}
                                        />
                                    )}
                                </Tag>
                            </button>
                        );
                    })}
                </div>
            </Field>
            <div
                style={{
                    display: 'grid',
                    gridTemplateColumns: '1fr 1fr',
                    gap: 16,
                }}
            >
                <Field label="Экстренные номера">
                    <Input
                        value={data.contacts.ru ?? ''}
                        onChange={(e) =>
                            setData('contacts', {
                                ...data.contacts,
                                ru: e.target.value,
                            })
                        }
                        placeholder="112 · +992 (37) 221-59-00"
                    />
                </Field>
                <Field label="Категория риска">
                    <Select
                        placeholder="Не указана"
                        value={data.risk_category}
                        options={reference.riskCategories}
                        onChange={(e) =>
                            setData('risk_category', e.target.value)
                        }
                    />
                </Field>
            </div>
        </div>
    );
}

function Step5({
    data,
    setData,
    reference,
    publishMode,
    setPublishMode,
    preview,
    setPreview,
    toggleChannel,
    checklist,
}: {
    data: any;
    setData: (k: string, v: unknown) => void;
    reference: Reference;
    publishMode: 'now' | 'schedule' | 'review';
    setPublishMode: (m: 'now' | 'schedule' | 'review') => void;
    preview: 'desktop' | 'mobile';
    setPreview: (p: 'desktop' | 'mobile') => void;
    toggleChannel: (v: string) => void;
    checklist: { label: string; ok: boolean }[];
}) {
    return (
        <div
            style={{
                display: 'grid',
                gridTemplateColumns: 'minmax(0,1fr) 420px',
                gap: 20,
            }}
            className="cms-two-col"
        >
            <div
                style={{
                    maxWidth: 520,
                    display: 'flex',
                    flexDirection: 'column',
                    gap: 16,
                }}
            >
                <Field label="Способ публикации">
                    <div
                        style={{
                            display: 'flex',
                            flexDirection: 'column',
                            gap: 8,
                        }}
                    >
                        <Radio
                            name="pm"
                            label="Опубликовать сейчас"
                            checked={publishMode === 'now'}
                            onChange={() => setPublishMode('now')}
                        />
                        <Radio
                            name="pm"
                            label="Запланировать дату и время"
                            checked={publishMode === 'schedule'}
                            onChange={() => setPublishMode('schedule')}
                        />
                        <Radio
                            name="pm"
                            label="Отправить на согласование руководителю"
                            checked={publishMode === 'review'}
                            onChange={() => setPublishMode('review')}
                        />
                    </div>
                </Field>
                {publishMode === 'schedule' && (
                    <Field label="Дата и время публикации">
                        <DatePicker
                            withTime
                            value={data.scheduled_at}
                            onChange={(e) =>
                                setData('scheduled_at', e.target.value)
                            }
                        />
                    </Field>
                )}
                {publishMode === 'review' && (
                    <Field label="Согласующий">
                        <Select
                            placeholder="Выберите согласующего"
                            value={data.approver_id}
                            onChange={(e) =>
                                setData(
                                    'approver_id',
                                    e.target.value
                                        ? Number(e.target.value)
                                        : '',
                                )
                            }
                        >
                            {reference.approvers.map((a) => (
                                <option key={a.id} value={a.id}>
                                    {a.name}
                                </option>
                            ))}
                        </Select>
                    </Field>
                )}
                <Field label="Каналы публикации">
                    <div
                        style={{
                            display: 'flex',
                            flexDirection: 'column',
                            gap: 8,
                        }}
                    >
                        {reference.channels
                            .filter((c) => c.value !== 'sms')
                            .map((c) => (
                                <Checkbox
                                    key={c.value}
                                    label={c.label}
                                    checked={data.channels.includes(c.value)}
                                    onChange={() => toggleChannel(c.value)}
                                />
                            ))}
                    </div>
                </Field>
            </div>

            <div>
                <div
                    style={{
                        display: 'flex',
                        alignItems: 'center',
                        justifyContent: 'space-between',
                        marginBottom: 8,
                    }}
                >
                    <div className="ui-kicker">
                        Предпросмотр · карточка на главной
                    </div>
                    <Segmented
                        value={preview}
                        onChange={setPreview}
                        options={[
                            { value: 'desktop', label: 'Desktop' },
                            { value: 'mobile', label: 'Mobile' },
                        ]}
                    />
                </div>
                <Blueprint
                    style={{
                        padding: 14,
                        borderTop: '3px solid var(--sev-warning)',
                        maxWidth: preview === 'mobile' ? 320 : '100%',
                    }}
                >
                    <SeverityBadge severity={data.severity as Severity} />
                    <div
                        style={{
                            fontFamily: 'var(--font-heading)',
                            fontWeight: 600,
                            fontSize: 16,
                            marginTop: 8,
                        }}
                    >
                        {data.title.ru || 'Заголовок предупреждения'}
                    </div>
                    <div
                        style={{
                            fontSize: 12,
                            color: 'var(--color-neutral-600)',
                            marginTop: 4,
                        }}
                    >
                        {data.ends_at
                            ? `Действует до ${new Date(data.ends_at).toLocaleString('ru-RU', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' })}`
                            : 'Срок не задан'}
                    </div>
                    <p style={{ fontSize: 13, marginTop: 8, marginBottom: 0 }}>
                        {data.summary.ru || 'Краткое описание появится здесь.'}
                    </p>
                </Blueprint>
                <div style={{ marginTop: 16 }}>
                    <ChecklistPanel checklist={checklist} />
                </div>
            </div>
        </div>
    );
}

function ChecklistPanel({
    checklist,
    compAll,
}: {
    checklist: { label: string; ok: boolean }[];
    compAll?: Record<string, number>;
}) {
    return (
        <Blueprint
            style={{
                padding: 14,
                position: 'sticky',
                top: 70,
                alignSelf: 'flex-start',
            }}
        >
            <div className="ui-kicker" style={{ marginBottom: 10 }}>
                Проверка перед публикацией
            </div>
            <div style={{ display: 'flex', flexDirection: 'column', gap: 7 }}>
                {checklist.map((c, i) => (
                    <div
                        key={i}
                        style={{
                            display: 'flex',
                            alignItems: 'center',
                            gap: 8,
                            fontSize: 13,
                        }}
                    >
                        {c.ok ? (
                            <CheckCircle2
                                size={15}
                                strokeWidth={1.5}
                                style={{ color: 'var(--ok)' }}
                            />
                        ) : (
                            <TriangleAlert
                                size={15}
                                strokeWidth={1.5}
                                style={{ color: 'var(--warn)' }}
                            />
                        )}
                        <span
                            style={{
                                color: c.ok
                                    ? 'var(--color-text)'
                                    : 'var(--warn)',
                            }}
                        >
                            {c.label}
                        </span>
                    </div>
                ))}
            </div>
            {compAll && (
                <div
                    style={{
                        marginTop: 12,
                        paddingTop: 10,
                        borderTop: '1px solid var(--color-divider)',
                        fontSize: 11.5,
                        color: 'var(--color-neutral-600)',
                    }}
                >
                    Заполненность: ТҶ {compAll.tg}% · РУ {compAll.ru}% · EN{' '}
                    {compAll.en}%
                </div>
            )}
        </Blueprint>
    );
}
