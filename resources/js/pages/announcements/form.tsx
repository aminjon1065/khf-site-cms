import { useForm } from '@inertiajs/react';
import { useState } from 'react';
import { EditorialFormShell } from '@/cms/EditorialFormShell';
import { useCan } from '@/lib/auth';
import type { ContentLocale, ContentStatus } from '@/lib/domain';
import { index, store, update } from '@/routes/announcements';
import { Blueprint } from '@/ui/Blueprint';
import { DatePicker, Field, Input, Select, Textarea } from '@/ui/Field';

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
}

const EMPTY: LocaleMap = { ru: '', tg: '', en: '' };

export default function AnnouncementForm({ announcement, reference }: Props) {
    const can = useCan();
    const isEdit = !!announcement;
    const [lang, setLang] = useState<ContentLocale>('ru');

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
        });
    };

    return (
        <EditorialFormShell
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
            onSubmitReview={() => submit('submit', 'review')}
            onPublishNow={() => submit('submit', 'now')}
            autosave={{
                contentType: 'announcements',
                contentId: announcement?.id ?? null,
                baseVersion: announcement?.updated_at ?? null,
                data,
                onRecover: (recovered) =>
                    form.setData({ ...data, ...recovered }),
            }}
            preview={{
                locales: {
                    tg: { title: data.title.tg, body: data.body.tg },
                    ru: { title: data.title.ru, body: data.body.ru },
                    en: { title: data.title.en, body: data.body.en },
                },
                signedUrl: announcement?.preview_url,
                checklist: [
                    {
                        label: 'Русская версия заполнена',
                        ok: compAll.ru === 100,
                        blocking: true,
                    },
                    {
                        label: 'Таджикская версия заполнена',
                        ok: compAll.tg === 100,
                        blocking: true,
                    },
                    {
                        label: 'Срок указан',
                        ok: data.deadline !== '',
                    },
                ],
            }}
        >
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
                                hasError={
                                    lang === 'ru' && !!fieldError('title.ru')
                                }
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
                    style={{
                        display: 'flex',
                        flexDirection: 'column',
                        gap: 16,
                    }}
                >
                    <Blueprint style={{ padding: 20 }}>
                        <h3
                            className="ui-card-title"
                            style={{ marginTop: 0, marginBottom: 14 }}
                        >
                            Параметры
                        </h3>

                        <Field
                            label="Адрес (slug)"
                            hint="Оставьте пустым — сгенерируется из русского заголовка."
                            error={fieldError('slug')}
                        >
                            <Input
                                value={data.slug}
                                onChange={(e) =>
                                    setData('slug', e.target.value)
                                }
                                placeholder="operator-sluzhby-112"
                                className="ui-mono"
                                maxLength={255}
                            />
                        </Field>

                        <Field
                            label="Тип объявления"
                            required
                            error={fieldError('kind')}
                        >
                            <Select
                                value={data.kind}
                                options={reference.kinds}
                                onChange={(e) =>
                                    setData('kind', e.target.value)
                                }
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

                        {/* Тендер принадлежит проекту: связь нужна, чтобы на
                            странице проекта появился блок «Тендеры проекта», а
                            из объявления был путь обратно к проекту. */}
                        {data.kind === 'tender' && (
                            <Field
                                label="Проект"
                                hint="Тендер появится в списке тендеров этого проекта."
                                error={fieldError('project_id')}
                            >
                                <Select
                                    value={String(data.project_id ?? '')}
                                    options={[
                                        { value: '', label: 'Вне проекта' },
                                        ...reference.projects,
                                    ]}
                                    onChange={(e) =>
                                        setData(
                                            'project_id',
                                            e.target.value === ''
                                                ? ''
                                                : Number(e.target.value),
                                        )
                                    }
                                />
                            </Field>
                        )}

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

                        <Field
                            label="Ссылка для подачи заявки"
                            hint="Внутренний путь /..., HTTPS, mailto: или tel:."
                            error={fieldError('application_url')}
                        >
                            <Input
                                value={data.application_url}
                                onChange={(e) =>
                                    setData('application_url', e.target.value)
                                }
                                placeholder="/contacts или https://example.tj/form"
                                maxLength={2048}
                            />
                        </Field>
                    </Blueprint>
                </div>
            </div>
        </EditorialFormShell>
    );
}
