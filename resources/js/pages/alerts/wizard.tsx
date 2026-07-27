import { Head, router, useForm } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import AlertController from '@/actions/App/Http/Controllers/Cms/AlertController';
import type { ContentLocale } from '@/lib/domain';
import { useT } from '@/lib/i18n';
import { Tag } from '@/ui/Badge';
import { Blueprint } from '@/ui/Blueprint';
import { Button, IconButton } from '@/ui/Button';
import { Stepper } from '@/ui/Nav';
import { Step1 } from './wizard/Step1';
import { Step2 } from './wizard/Step2';
import { Step3 } from './wizard/Step3';
import { Step4 } from './wizard/Step4';
import { Step5 } from './wizard/Step5';
import type { Localized, Reference } from './wizard/types';

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
                AlertController.update.url(alert.id),
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
            router.put(AlertController.update.url(alert.id), payload, opts);
        } else {
            router.post(AlertController.store.url(), payload, opts);
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
                    onClick={() => router.visit(AlertController.index.url())}
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
