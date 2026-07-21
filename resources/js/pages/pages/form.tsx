import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, ChevronDown, Save, Send } from 'lucide-react';
import { useState } from 'react';
import { useCan } from '@/lib/auth';
import type { ContentLocale, ContentStatus } from '@/lib/domain';
import { StatusBadge } from '@/ui/Badge';
import { Blueprint } from '@/ui/Blueprint';
import { Button, LinkButton } from '@/ui/Button';
import { Field, Input, Select, Textarea } from '@/ui/Field';
import { LanguageTabs } from '@/ui/Nav';
import { Dropdown } from '@/ui/Overlay';
import { PageHeader } from '@/ui/PageHeader';

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
    slug: string;
    status: ContentStatus;
    parent_id: number | null;
    sort: number;
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
        slug: page?.slug ?? '',
        parent_id: (page?.parent_id ?? '') as number | '',
        sort: page?.sort ?? 0,
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
            ...(isEdit ? { _method: 'put' } : {}),
        }));

        form.post(isEdit ? `/pages/${page!.id}` : '/pages', {
            preserveScroll: true,
        });
    };

    return (
        <>
            <Head
                title={isEdit ? 'Редактирование страницы' : 'Новая страница'}
            />

            <PageHeader
                eyebrow={
                    <Link
                        href="/pages"
                        style={{
                            display: 'inline-flex',
                            alignItems: 'center',
                            gap: 6,
                            color: 'var(--color-neutral-600)',
                            textDecoration: 'none',
                        }}
                    >
                        <ArrowLeft size={14} strokeWidth={1.75} /> Страницы
                        сайта
                    </Link>
                }
                title={isEdit ? 'Редактирование страницы' : 'Новая страница'}
                subtitle="Информационная страница портала. Текст сохраняется как обычный текст (абзацы разделяются пустой строкой)."
                actions={
                    isEdit && page ? <StatusBadge status={page.status} /> : null
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
                            hint="Абзацы разделяются пустой строкой."
                        >
                            <Textarea
                                value={data.body[lang]}
                                onChange={(e) =>
                                    setLocaleField('body', e.target.value)
                                }
                                style={{ minHeight: 320 }}
                                maxLength={50000}
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

            {/* --------------------------------------------- sticky actions */}
            <div className="news-form-actions">
                <LinkButton href="/pages" variant="ghost">
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
                {can('pages.publish') ? (
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
