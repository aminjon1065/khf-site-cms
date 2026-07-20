import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, ChevronDown, Images, Save, Send, Upload } from 'lucide-react';
import { useRef, useState } from 'react';
import { useSaveShortcut } from '@/hooks/use-save-shortcut';
import { useCan } from '@/lib/auth';
import type { ContentLocale, ContentStatus } from '@/lib/domain';
import { StatusBadge } from '@/ui/Badge';
import { Blueprint } from '@/ui/Blueprint';
import { Button, LinkButton } from '@/ui/Button';
import {
    Checkbox,
    DatePicker,
    Field,
    Input,
    Select,
    Textarea,
} from '@/ui/Field';
import { MediaPicker  } from '@/ui/MediaPicker';
import type {MediaItem} from '@/ui/MediaPicker';
import { LanguageTabs } from '@/ui/Nav';
import { Dropdown } from '@/ui/Overlay';
import { PageHeader } from '@/ui/PageHeader';
import { RichEditor } from '@/ui/RichEditor';

type LocaleMap = { ru: string; tg: string; en: string };
type PublishMode = 'now' | 'schedule' | 'review';

interface Option {
    value: number;
    label: string;
}

interface NewsData {
    id: number;
    title: LocaleMap;
    summary: LocaleMap;
    body: LocaleMap;
    slug: string | null;
    status: ContentStatus;
    category_id: number | null;
    tags: number[];
    cover_alt: string | null;
    cover_url: string | null;
    is_pinned: boolean;
    show_on_home: boolean;
    seo: { title: string; description: string };
    scheduled_at: string | null;
    published_at: string | null;
    views_count: number;
    languages: Record<string, number>;
}

interface Props {
    news: NewsData | null;
    reference: {
        categories: Option[];
        tags: Option[];
        authors: Option[];
    };
}

const EMPTY: LocaleMap = { ru: '', tg: '', en: '' };
const CONTENT_FIELDS: ('title' | 'summary' | 'body')[] = [
    'title',
    'summary',
    'body',
];

export default function NewsForm({ news, reference }: Props) {
    const can = useCan();
    const isEdit = !!news;
    const [lang, setLang] = useState<ContentLocale>('ru');
    const [coverPicker, setCoverPicker] = useState(false);
    // Превью обложки: для загрузки файла строим из File, для выбора из медиатеки
    // берём URL ассета; иначе показываем существующую news.cover_url.
    const [coverPreview, setCoverPreview] = useState<string | null>(null);
    const coverFileRef = useRef<HTMLInputElement>(null);

    const form = useForm({
        title: { ...EMPTY, ...news?.title } as LocaleMap,
        summary: { ...EMPTY, ...news?.summary } as LocaleMap,
        body: { ...EMPTY, ...news?.body } as LocaleMap,
        slug: news?.slug ?? '',
        category_id: (news?.category_id ?? '') as number | '',
        tags: (news?.tags ?? []) as number[],
        cover: null as File | null,
        cover_media_id: null as number | null,
        cover_remove: false,
        cover_alt: news?.cover_alt ?? '',
        is_pinned: news?.is_pinned ?? false,
        show_on_home: news?.show_on_home ?? true,
        seo: {
            title: news?.seo?.title ?? '',
            description: news?.seo?.description ?? '',
        },
        scheduled_at: news?.scheduled_at ?? '',
        publish_mode: 'review' as PublishMode,
        action: 'draft' as 'draft' | 'submit',
    });
    const { data, setData, processing, errors } = form;

    // Laravel returns dotted keys for nested fields (title.ru, seo.title).
    const fieldError = (key: string): string | undefined =>
        (errors as Record<string, string | undefined>)[key];

    const pickCoverFromLibrary = (item: MediaItem) => {
        setData('cover', null);
        setData('cover_media_id', item.id);
        setData('cover_remove', false);
        setCoverPreview(item.url);

        if (!data.cover_alt && item.name) {
            setData('cover_alt', item.name);
        }

        setCoverPicker(false);
    };

    // Что показать в превью обложки: свежий выбор (файл/медиатека) приоритетнее
    // существующей обложки; при отметке «убрать» превью скрывается.
    const coverSrc =
        coverPreview ??
        (news?.cover_url && !data.cover_remove ? news.cover_url : null);

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
    ) => {
        setData(field, { ...data[field], [lang]: value });
    };

    const toggleTag = (id: number) => {
        setData(
            'tags',
            data.tags.includes(id)
                ? data.tags.filter((t) => t !== id)
                : [...data.tags, id],
        );
    };

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

        form.post(isEdit ? `/news/${news!.id}` : '/news', {
            forceFormData: true,
            preserveScroll: true,
            preserveState: stay,
        });
    };

    // Ctrl/Cmd+S — сохранить черновик и остаться в редакторе (stay = true).
    useSaveShortcut(() => submit('draft', undefined, true), !processing);

    return (
        <>
            <Head title={isEdit ? 'Редактирование новости' : 'Новая новость'} />

            <PageHeader
                eyebrow={
                    <Link
                        href="/news"
                        style={{
                            display: 'inline-flex',
                            alignItems: 'center',
                            gap: 6,
                            color: 'var(--color-neutral-600)',
                            textDecoration: 'none',
                        }}
                    >
                        <ArrowLeft size={14} strokeWidth={1.75} /> Новости
                    </Link>
                }
                title={isEdit ? 'Редактирование новости' : 'Новая новость'}
                subtitle={
                    isEdit
                        ? 'Изменения сохраняются как новая ревизия материала.'
                        : 'Заполните заголовок и текст, затем сохраните черновик или отправьте на согласование.'
                }
                actions={
                    isEdit && news ? <StatusBadge status={news.status} /> : null
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
                                        ? 'Например: КЧС провёл учения…'
                                        : 'Перевод заголовка'
                                }
                                maxLength={255}
                            />
                        </Field>

                        <Field
                            label="Краткое описание"
                            hint="Показывается в списке новостей и в предпросмотре ссылки."
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

                        <Field label="Текст новости">
                            <RichEditor
                                key={lang}
                                value={data.body[lang]}
                                onChange={(html) => setLocaleField('body', html)}
                                placeholder="Начните писать текст новости…"
                            />
                        </Field>
                    </Blueprint>

                    <Blueprint style={{ padding: 20 }}>
                        <h3
                            className="ui-card-title"
                            style={{ marginTop: 0, marginBottom: 14 }}
                        >
                            SEO и адрес
                        </h3>
                        <Field
                            label="Адрес (slug)"
                            hint="Оставьте пустым — сгенерируется автоматически из заголовка."
                            error={fieldError('slug')}
                        >
                            <Input
                                value={data.slug}
                                onChange={(e) => setData('slug', e.target.value)}
                                hasError={!!fieldError('slug')}
                                placeholder="naprimer-ucheniya-2026"
                            />
                        </Field>
                        <Field
                            label="SEO-заголовок"
                            error={fieldError('seo.title')}
                        >
                            <Input
                                value={data.seo.title}
                                onChange={(e) =>
                                    setData('seo', {
                                        ...data.seo,
                                        title: e.target.value,
                                    })
                                }
                                maxLength={255}
                            />
                        </Field>
                        <Field
                            label="SEO-описание"
                            error={fieldError('seo.description')}
                        >
                            <Textarea
                                value={data.seo.description}
                                onChange={(e) =>
                                    setData('seo', {
                                        ...data.seo,
                                        description: e.target.value,
                                    })
                                }
                                style={{ minHeight: 60 }}
                                maxLength={500}
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

                        <Field label="Категория" error={fieldError('category_id')}>
                            <Select
                                value={
                                    data.category_id === ''
                                        ? ''
                                        : String(data.category_id)
                                }
                                onChange={(e) =>
                                    setData(
                                        'category_id',
                                        e.target.value === ''
                                            ? ''
                                            : Number(e.target.value),
                                    )
                                }
                                placeholder="Без категории"
                                options={reference.categories.map((c) => ({
                                    value: c.value,
                                    label: c.label,
                                }))}
                            />
                        </Field>

                        {reference.tags.length > 0 && (
                            <Field label="Теги">
                                <div
                                    style={{
                                        display: 'flex',
                                        flexWrap: 'wrap',
                                        gap: 8,
                                    }}
                                >
                                    {reference.tags.map((t) => (
                                        <Checkbox
                                            key={t.value}
                                            label={t.label}
                                            checked={data.tags.includes(t.value)}
                                            onChange={() => toggleTag(t.value)}
                                        />
                                    ))}
                                </div>
                            </Field>
                        )}

                        <Field label="Дата запланированной публикации">
                            <DatePicker
                                withTime
                                value={data.scheduled_at}
                                onChange={(e) =>
                                    setData('scheduled_at', e.target.value)
                                }
                            />
                        </Field>

                        <div
                            style={{
                                display: 'flex',
                                flexDirection: 'column',
                                gap: 10,
                                marginTop: 4,
                            }}
                        >
                            <Checkbox
                                label="Закрепить материал"
                                checked={data.is_pinned}
                                onChange={(e) =>
                                    setData('is_pinned', e.target.checked)
                                }
                            />
                            <Checkbox
                                label="Показывать на главной"
                                checked={data.show_on_home}
                                onChange={(e) =>
                                    setData('show_on_home', e.target.checked)
                                }
                            />
                        </div>
                    </Blueprint>

                    <Blueprint style={{ padding: 20 }}>
                        <h3
                            className="ui-card-title"
                            style={{ marginTop: 0, marginBottom: 14 }}
                        >
                            Обложка
                        </h3>
                        {coverSrc && (
                            <img
                                src={coverSrc}
                                alt={data.cover_alt}
                                style={{
                                    width: '100%',
                                    borderRadius: 6,
                                    marginBottom: 10,
                                    border: '1px solid var(--color-divider)',
                                }}
                            />
                        )}

                        <input
                            ref={coverFileRef}
                            type="file"
                            accept="image/png,image/jpeg,image/webp"
                            hidden
                            onChange={(e) => {
                                const file = e.target.files?.[0] ?? null;

                                if (file) {
                                    setData('cover', file);
                                    setData('cover_media_id', null);
                                    setData('cover_remove', false);
                                    setCoverPreview(URL.createObjectURL(file));
                                }

                                e.target.value = '';
                            }}
                        />
                        <div
                            style={{ display: 'flex', gap: 8, flexWrap: 'wrap' }}
                        >
                            <Button
                                variant="secondary"
                                icon={<Upload size={15} strokeWidth={1.75} />}
                                onClick={() => coverFileRef.current?.click()}
                            >
                                Загрузить
                            </Button>
                            <Button
                                variant="secondary"
                                icon={<Images size={15} strokeWidth={1.75} />}
                                onClick={() => setCoverPicker(true)}
                            >
                                Из медиатеки
                            </Button>
                        </div>

                        {fieldError('cover') && (
                            <div
                                style={{
                                    color: 'var(--danger)',
                                    fontSize: 12,
                                    marginTop: 6,
                                }}
                            >
                                {fieldError('cover')}
                            </div>
                        )}
                        {news?.cover_url && (
                            <Checkbox
                                className="mt-2"
                                label="Удалить текущую обложку"
                                checked={data.cover_remove}
                                onChange={(e) =>
                                    setData('cover_remove', e.target.checked)
                                }
                            />
                        )}
                        <Field label="Alt-текст обложки" className="mt-2">
                            <Input
                                value={data.cover_alt}
                                onChange={(e) =>
                                    setData('cover_alt', e.target.value)
                                }
                                placeholder="Описание изображения"
                            />
                        </Field>

                        <MediaPicker
                            open={coverPicker}
                            onClose={() => setCoverPicker(false)}
                            onSelect={pickCoverFromLibrary}
                        />
                    </Blueprint>
                </div>
            </div>

            {/* --------------------------------------------- sticky actions */}
            <div className="news-form-actions">
                <LinkButton href="/news" variant="ghost">
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
                {can('news.publish') ? (
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
                            {
                                label: 'Запланировать публикацию',
                                onSelect: () => submit('submit', 'schedule'),
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
