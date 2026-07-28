import { useForm } from '@inertiajs/react';
import { useState } from 'react';
import { EditorialFormShell } from '@/cms/EditorialFormShell';
import { useCan } from '@/lib/auth';
import type { ContentLocale, ContentStatus } from '@/lib/domain';
import { index, store, update } from '@/routes/pages';
import { Blueprint } from '@/ui/Blueprint';
import { Field, Input, Select, Textarea } from '@/ui/Field';
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

    const compAll = {
        tg:
            (data.title.tg.trim() !== '' ? 25 : 0) +
            (data.body.tg.trim() !== '' ? 25 : 0) +
            (data.seo_title.tg.trim() !== '' ? 25 : 0) +
            (data.seo_description.tg.trim() !== '' ? 25 : 0),
        ru:
            (data.title.ru.trim() !== '' ? 25 : 0) +
            (data.body.ru.trim() !== '' ? 25 : 0) +
            (data.seo_title.ru.trim() !== '' ? 25 : 0) +
            (data.seo_description.ru.trim() !== '' ? 25 : 0),
        en:
            (data.title.en.trim() !== '' ? 25 : 0) +
            (data.body.en.trim() !== '' ? 25 : 0) +
            (data.seo_title.en.trim() !== '' ? 25 : 0) +
            (data.seo_description.en.trim() !== '' ? 25 : 0),
    };

    const setLocaleField = (
        field: 'title' | 'body' | 'seo_title' | 'seo_description',
        value: string,
    ) => setData(field, { ...data[field], [lang]: value });

    const parentOptions: Option[] = [
        { value: '', label: '— Верхний уровень —' },
        ...reference.parents.map((p) => ({
            value: String(p.value),
            label: p.label,
        })),
    ];

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
            title={isEdit ? 'Редактирование страницы' : 'Новая страница'}
            subtitle="Редакционная страница портала с локализованным rich text и SEO."
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
                        label: 'SEO preview заполнен',
                        ok:
                            data.seo_title.ru.trim() !== '' &&
                            data.seo_description.ru.trim() !== '',
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
                                Содержание
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
                                        ? 'Например: О Комитете'
                                        : 'Перевод заголовка'
                                }
                                maxLength={255}
                            />
                        </Field>

                        <Field
                            label="Текст страницы"
                            hint="Форматирование очищается на сервере перед публикацией."
                        >
                            <RichEditor
                                value={data.body[lang]}
                                onChange={(value) =>
                                    setLocaleField('body', value)
                                }
                                placeholder="Текст страницы"
                            />
                        </Field>

                        <Field
                            label="SEO title"
                            error={fieldError(`seo_title.${lang}`)}
                        >
                            <Input
                                value={data.seo_title[lang]}
                                onChange={(e) =>
                                    setLocaleField('seo_title', e.target.value)
                                }
                                maxLength={70}
                            />
                        </Field>

                        <Field
                            label="SEO description"
                            error={fieldError(`seo_description.${lang}`)}
                        >
                            <Textarea
                                value={data.seo_description[lang]}
                                onChange={(e) =>
                                    setLocaleField(
                                        'seo_description',
                                        e.target.value,
                                    )
                                }
                                maxLength={180}
                                style={{ minHeight: 96 }}
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
                            hint="Оставьте пустым — сгенерируется из заголовка."
                            error={fieldError('slug')}
                        >
                            <Input
                                value={data.slug}
                                onChange={(e) =>
                                    setData('slug', e.target.value)
                                }
                                placeholder="about"
                                className="ui-mono"
                                maxLength={255}
                            />
                        </Field>

                        <Field
                            label="Родительская страница"
                            error={fieldError('parent_id')}
                        >
                            <Select
                                value={
                                    data.parent_id === ''
                                        ? ''
                                        : String(data.parent_id)
                                }
                                options={parentOptions}
                                onChange={(e) =>
                                    setData(
                                        'parent_id',
                                        e.target.value === ''
                                            ? ''
                                            : Number(e.target.value),
                                    )
                                }
                            />
                        </Field>

                        <Field label="Порядок" error={fieldError('sort')}>
                            <Input
                                type="number"
                                min={0}
                                value={String(data.sort)}
                                onChange={(e) =>
                                    setData('sort', Number(e.target.value) || 0)
                                }
                            />
                        </Field>
                    </Blueprint>
                </div>
            </div>
        </EditorialFormShell>
    );
}
