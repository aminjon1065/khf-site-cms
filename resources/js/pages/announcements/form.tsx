import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, ChevronDown, Save, Send } from 'lucide-react';
import { useState } from 'react';
import { useCan } from '@/lib/auth';
import type { ContentLocale, ContentStatus } from '@/lib/domain';
import { StatusBadge } from '@/ui/Badge';
import { Blueprint } from '@/ui/Blueprint';
import { Button, LinkButton } from '@/ui/Button';
import { DatePicker, Field, Input, Select, Textarea } from '@/ui/Field';
import { LanguageTabs } from '@/ui/Nav';
import { Dropdown } from '@/ui/Overlay';
import { PageHeader } from '@/ui/PageHeader';

type LocaleMap = { ru: string; tg: string; en: string };
type PublishMode = 'now' | 'schedule' | 'review';

interface Option {
    value: string;
    label: string;
}

interface AnnouncementData {
    id: number;
    title: LocaleMap;
    body: LocaleMap;
    kind: string;
    org: string | null;
    deadline: string | null;
    status: ContentStatus;
    is_open: boolean;
}

interface Props {
    announcement: AnnouncementData | null;
    reference: {
        kinds: Option[];
        authors: Option[];
    };
}

const EMPTY: LocaleMap = { ru: '', tg: '', en: '' };

export default function AnnouncementForm({ announcement, reference }: Props) {
    const can = useCan();
    const isEdit = !!announcement;
    const [lang, setLang] = useState<ContentLocale>('ru');

    const form = useForm({
        title: { ...EMPTY, ...announcement?.title } as LocaleMap,
        body: { ...EMPTY, ...announcement?.body } as LocaleMap,
        kind: (announcement?.kind ?? 'vacancy') as string,
        org: announcement?.org ?? '',
        deadline: announcement?.deadline ?? '',
        publish_mode: 'review' as PublishMode,
        action: 'draft' as 'draft' | 'submit',
    });
    const { data, setData, processing, errors } = form;

    const fieldError = (key: string): string | undefined =>
        (errors as Record<string, string | undefined>)[key];

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

    const setLocaleField = (field: 'title' | 'body', value: string) =>
        setData(field, { ...data[field], [lang]: value });

    const submit = (action: 'draft' | 'submit', mode?: PublishMode) => {
        form.transform((d) => ({
            ...d,
            action,
            publish_mode: mode ?? d.publish_mode,
            ...(isEdit ? { _method: 'put' } : {}),
        }));

        form.post(
            isEdit ? `/announcements/${announcement!.id}` : '/announcements',
            { forceFormData: true, preserveScroll: true },
        );
    };

    return (
        <>
            <Head
                title={
                    isEdit ? 'Редактирование объявления' : 'Новое объявление'
                }
            />

            <PageHeader
                eyebrow={
                    <Link
                        href="/announcements"
                        style={{
                            display: 'inline-flex',
                            alignItems: 'center',
                            gap: 6,
                            color: 'var(--color-neutral-600)',
                            textDecoration: 'none',
                        }}
                    >
                        <ArrowLeft size={14} strokeWidth={1.75} /> Объявления
                    </Link>
                }
                title={
                    isEdit ? 'Редактирование объявления' : 'Новое объявление'
                }
                subtitle="Вакансия или тендер. Приём заявок закрывается автоматически после срока."
                actions={
                    isEdit && announcement ? (
                        <StatusBadge status={announcement.status} />
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
                                Текст объявления
                            </h3>
                            <LanguageTabs
                                active={lang}
                                onChange={setLang}
                                completeness={compAll}
                            />
                        </div>

                        <Field
                            label="Заголовок"
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
                                        ? 'Например: Оператор службы 112'
                                        : 'Перевод заголовка'
                                }
                                maxLength={255}
                            />
                        </Field>

                        <Field
                            label="Описание"
                            hint="Требования, условия участия, контакты."
                        >
                            <Textarea
                                value={data.body[lang]}
                                onChange={(e) =>
                                    setLocaleField('body', e.target.value)
                                }
                                style={{ minHeight: 160 }}
                                maxLength={5000}
                            />
                        </Field>
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
                            label="Тип объявления"
                            required
                            error={fieldError('kind')}
                        >
                            <Select
                                value={data.kind}
                                options={reference.kinds}
                                onChange={(e) => setData('kind', e.target.value)}
                            />
                        </Field>

                        <Field
                            label="Подразделение / проект"
                            error={fieldError('org')}
                        >
                            <Input
                                value={data.org}
                                onChange={(e) => setData('org', e.target.value)}
                                placeholder="Например: Отдел кадров"
                                maxLength={255}
                            />
                        </Field>

                        <Field
                            label="Срок подачи"
                            hint="После этой даты приём заявок закрывается."
                            error={fieldError('deadline')}
                        >
                            <DatePicker
                                value={data.deadline}
                                onChange={(e) =>
                                    setData('deadline', e.target.value)
                                }
                            />
                        </Field>
                    </Blueprint>
                </div>
            </div>

            {/* --------------------------------------------- sticky actions */}
            <div className="news-form-actions">
                <LinkButton href="/announcements" variant="ghost">
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
                {can('announcements.publish') ? (
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
