import { useForm } from '@inertiajs/react';
import { Sliders } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { EditorialFormShell } from '@/cms/EditorialFormShell';
import { useCan } from '@/lib/auth';
import type { ContentLocale, ContentStatus } from '@/lib/domain';
import { languageChecks } from '@/lib/publication-languages';
import { index, store, update } from '@/routes/news';
import { Button } from '@/ui/Button';
import { MediaPicker } from '@/ui/MediaPicker';
import type { MediaItem } from '@/ui/MediaPicker';
import { RichEditor } from '@/ui/RichEditor';
import type { ActiveBlockInfo, RichGalleryContextValue } from '@/ui/RichEditor';
import { NewsInspectorSidebar } from './NewsInspectorSidebar';

type LocaleMap = { ru: string; tg: string; en: string };
type SeoFields = { title: string; description: string };
type SeoLocaleMap = Record<ContentLocale, SeoFields>;
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
    attachments?: { id: number; title: string; ext: string; size: string }[];
    cover_caption: string | null;
    cover_url: string | null;
    gallery?: { id: number; title: string; preview_url: string }[];
    is_pinned: boolean;
    show_on_home: boolean;
    seo: SeoLocaleMap;
    scheduled_at: string | null;
    published_at: string | null;
    views_count: number;
    languages: Record<string, number>;
    updated_at: string;
    preview_url: string;
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
const EMPTY_SEO: SeoFields = { title: '', description: '' };
const CONTENT_FIELDS: ('title' | 'summary' | 'body')[] = [
    'title',
    'summary',
    'body',
];

export default function NewsForm({ news, reference }: Props) {
    const can = useCan();
    const isEdit = !!news;
    const [lang, setLang] = useState<ContentLocale>('ru');
    const [sidebarOpen, setSidebarOpen] = useState(true);
    const [sidebarTab, setSidebarTab] = useState<'post' | 'block'>('post');
    const [activeBlock, setActiveBlock] = useState<ActiveBlockInfo | null>(
        null,
    );
    const [coverPicker, setCoverPicker] = useState(false);
    const [galleryPicker, setGalleryPicker] = useState(false);
    // Снимки галереи, добавленные из медиатеки, но ещё не отправленные:
    // для них нужен локальный превью-список — File[] их не представляет.
    const [galleryPending, setGalleryPending] = useState<
        { id: number; title: string; url: string }[]
    >([]);
    // Превью обложки: для загрузки файла строим из File, для выбора из медиатеки
    // берём URL ассета; иначе показываем существующую news.cover_url.
    const [coverPreview, setCoverPreview] = useState<string | null>(null);
    const coverFileRef = useRef<HTMLInputElement>(null);
    const titleRef = useRef<HTMLTextAreaElement>(null);
    const summaryRef = useRef<HTMLTextAreaElement>(null);

    useEffect(() => {
        if (titleRef.current) {
            titleRef.current.style.height = 'auto';
            titleRef.current.style.height = `${titleRef.current.scrollHeight}px`;
        }

        if (summaryRef.current) {
            summaryRef.current.style.height = 'auto';
            summaryRef.current.style.height = `${summaryRef.current.scrollHeight}px`;
        }
    }, [lang]);

    const handleActiveBlockChange = (block: ActiveBlockInfo | null) => {
        setActiveBlock(block);

        if (
            block &&
            block.type !== 'paragraph' &&
            block.type !== 'blockquote'
        ) {
            setSidebarTab('block');
        }
    };

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
        attachments: [] as File[],
        attachments_remove: [] as number[],
        gallery: [] as File[],
        gallery_media_ids: [] as number[],
        gallery_remove: [] as number[],
        cover_alt: news?.cover_alt ?? '',
        cover_caption: news?.cover_caption ?? '',
        is_pinned: news?.is_pinned ?? false,
        show_on_home: news?.show_on_home ?? true,
        seo: {
            ru: { ...EMPTY_SEO, ...news?.seo?.ru },
            tg: { ...EMPTY_SEO, ...news?.seo?.tg },
            en: { ...EMPTY_SEO, ...news?.seo?.en },
        } as SeoLocaleMap,
        scheduled_at: news?.scheduled_at ?? '',
        publish_mode: 'review' as PublishMode,
        action: 'draft' as 'draft' | 'submit',
    });
    const { data, setData, processing, errors, isDirty } = form;

    // Laravel returns dotted keys for nested fields (title.ru, seo.ru.title).
    const fieldError = (key: string): string | undefined =>
        (errors as Record<string, string | undefined>)[key];

    const pickCoverFromLibrary = (item: MediaItem) => {
        setData('cover', null);
        setData('cover_media_id', item.id);
        setData('cover_remove', false);
        setCoverPreview(item.url);

        if (!data.cover_alt && (item.alt || item.name)) {
            setData('cover_alt', item.alt ?? item.name ?? '');
        }

        setCoverPicker(false);
    };

    // Мультивыбор из медиатеки: отмеченные снимки дописываются в конец
    // галереи в порядке отметки — он же порядок кадров в карусели.
    const addGalleryFromLibrary = (picked: MediaItem[]) => {
        setData('gallery_media_ids', [
            ...data.gallery_media_ids,
            ...picked.map((item) => item.id),
        ]);
        setGalleryPending((pending) => [
            ...pending,
            ...picked.map((item) => ({
                id: item.id,
                title: item.alt || item.name || '',
                url: item.url,
            })),
        ]);
    };

    const handleAddGalleryFiles = (files: File[]) => {
        setData('gallery', [...data.gallery, ...files]);
    };

    const handleToggleGalleryRemove = (id: number | string) => {
        if (typeof id === 'number') {
            setData(
                'gallery_remove',
                data.gallery_remove.includes(id)
                    ? data.gallery_remove.filter((x) => x !== id)
                    : [...data.gallery_remove, id],
            );
        } else if (typeof id === 'string') {
            if (id.startsWith('lib-')) {
                const numId = Number(id.replace('lib-', ''));
                setData(
                    'gallery_media_ids',
                    data.gallery_media_ids.filter((x) => x !== numId),
                );
                setGalleryPending((pending) =>
                    pending.filter((x) => x.id !== numId),
                );
            } else if (id.startsWith('file-')) {
                const matchIndex = Number(id.split('-').pop());

                if (!Number.isNaN(matchIndex)) {
                    setData(
                        'gallery',
                        data.gallery.filter((_, idx) => idx !== matchIndex),
                    );
                }
            }
        }
    };

    const galleryContext: RichGalleryContextValue = {
        items: [
            ...(news?.gallery ?? []).map((it) => ({
                id: it.id,
                title: it.title,
                url: it.preview_url,
                isExisting: true,
                isRemoved: data.gallery_remove.includes(it.id),
            })),
            ...galleryPending.map((it) => ({
                id: `lib-${it.id}`,
                title: it.title,
                url: it.url,
                isPendingLibrary: true,
            })),
            ...data.gallery.map((file, idx) => ({
                id: `file-${file.name}-${idx}`,
                title: file.name,
                url: URL.createObjectURL(file),
                isPendingFile: true,
            })),
        ],
        onAddFiles: handleAddGalleryFiles,
        onAddLibrary: addGalleryFromLibrary,
        onToggleRemove: handleToggleGalleryRemove,
        onOpenPicker: () => setGalleryPicker(true),
    };

    // Что показать в превью обложки: свежий выбор (файл/медиатека) приоритетнее
    // существующей обложки; при отметке «убрать» превью скрывается.
    const coverSrc =
        coverPreview ??
        (news?.cover_url && !data.cover_remove ? news.cover_url : null);

    // Required fields only (title, lead, text), the same as
    // News::languageCompleteness(): the search snippet is optional.
    const completeness = (locale: ContentLocale): number => {
        const values = CONTENT_FIELDS.map((field) => data[field][locale]);
        const filled = values.filter((value) => value.trim() !== '').length;

        return Math.round((filled / values.length) * 100);
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

    const setSeoField = (field: keyof SeoFields, value: string) => {
        setData('seo', {
            ...data.seo,
            [lang]: { ...data.seo[lang], [field]: value },
        });
    };

    const toggleTag = (id: number) => {
        setData(
            'tags',
            data.tags.includes(id)
                ? data.tags.filter((t) => t !== id)
                : [...data.tags, id],
        );
    };

    const handleCopyLocale = (from: ContentLocale, to: ContentLocale) => {
        setData('title', { ...data.title, [to]: data.title[from] });
        setData('summary', { ...data.summary, [to]: data.summary[from] });
        setData('body', { ...data.body, [to]: data.body[from] });
        setData('seo', {
            ...data.seo,
            [to]: {
                title: data.seo[from].title,
                description: data.seo[from].description,
            },
        });
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
            ...(isEdit
                ? {
                      _method: 'put',
                      _editorial_version: news!.updated_at,
                  }
                : {}),
        }));

        form.post(isEdit ? update.url(news!.id) : store.url(), {
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
            variant="gutenberg"
            onCopyLocale={handleCopyLocale}
            title={isEdit ? 'Редактирование новости' : 'Новая новость'}
            subtitle={
                isEdit
                    ? 'Изменения сохраняются как новая ревизия материала.'
                    : 'Заполните заголовок и текст, затем сохраните черновик или отправьте на согласование.'
            }
            backLabel="Новости"
            backHref={index.url()}
            status={news?.status}
            language={{
                active: lang,
                onChange: setLang,
                completeness: compAll,
            }}
            errors={errors}
            isDirty={isDirty}
            processing={processing}
            canPublish={can('news.publish')}
            onSaveDraft={() => submit('draft')}
            onSaveShortcut={() => submit('draft', undefined, true)}
            onSubmitReview={() => submit('submit', 'review')}
            onPublishNow={() => submit('submit', 'now')}
            onSchedule={() => submit('submit', 'schedule')}
            autosave={{
                contentType: 'news',
                contentId: news?.id ?? null,
                baseVersion: news?.updated_at ?? null,
                data,
                onRecover: (recovered) =>
                    form.setData({ ...data, ...recovered }),
            }}
            preview={{
                locales: {
                    tg: {
                        title: data.title.tg,
                        summary: data.summary.tg,
                        body: data.body.tg,
                        seoTitle: data.seo.tg.title,
                        seoDescription: data.seo.tg.description,
                    },
                    ru: {
                        title: data.title.ru,
                        summary: data.summary.ru,
                        body: data.body.ru,
                        seoTitle: data.seo.ru.title,
                        seoDescription: data.seo.ru.description,
                    },
                    en: {
                        title: data.title.en,
                        summary: data.summary.en,
                        body: data.body.en,
                        seoTitle: data.seo.en.title,
                        seoDescription: data.seo.en.description,
                    },
                },
                imageUrl: coverSrc,
                imageAlt: data.cover_alt,
                signedUrl: news?.preview_url,
                checklist: [
                    ...languageChecks(compAll, data.title),
                    {
                        label: 'Alt-текст обложки',
                        ok: !coverSrc || data.cover_alt.trim() !== '',
                        blocking: true,
                    },
                    {
                        label: 'SEO preview заполнен',
                        ok: Object.values(data.seo).some(
                            (seo) =>
                                seo.title.trim() !== '' &&
                                seo.description.trim() !== '',
                        ),
                    },
                ],
            }}
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
        >
            <div
                className={`wp-editor-layout ${sidebarOpen ? 'has-sidebar' : 'no-sidebar'}`}
            >
                {/* ------------------------------------------- Document Canvas */}
                <main className="wp-editor-canvas-container" role="main">
                    <div className="wp-editor-canvas">
                        {/* Title field */}
                        <div className="wp-title-wrapper">
                            <textarea
                                ref={titleRef}
                                id={`news-title-${lang}`}
                                value={data.title[lang]}
                                onChange={(e) => {
                                    setLocaleField('title', e.target.value);
                                    e.target.style.height = 'auto';
                                    e.target.style.height = `${e.target.scrollHeight}px`;
                                }}
                                placeholder={
                                    lang === 'ru'
                                        ? 'Добавьте заголовок...'
                                        : lang === 'tg'
                                          ? 'Сарлавҳа илова кунед...'
                                          : 'Add title...'
                                }
                                className="wp-title-input"
                                rows={1}
                                maxLength={255}
                                aria-label="Заголовок новости"
                            />
                            {(fieldError('title') ??
                                fieldError(`title.${lang}`)) && (
                                <div className="wp-field-error">
                                    {fieldError('title') ??
                                        fieldError(`title.${lang}`)}
                                </div>
                            )}
                        </div>

                        {/* Lead / Summary field */}
                        <div className="wp-lead-wrapper">
                            <textarea
                                ref={summaryRef}
                                id={`news-summary-${lang}`}
                                value={data.summary[lang]}
                                onChange={(e) => {
                                    setLocaleField('summary', e.target.value);
                                    e.target.style.height = 'auto';
                                    e.target.style.height = `${e.target.scrollHeight}px`;
                                }}
                                placeholder={
                                    lang === 'ru'
                                        ? 'Краткое введение (лид) новости...'
                                        : lang === 'tg'
                                          ? 'Муқаддимаи мухтасар (лид)...'
                                          : 'Brief lead / summary of the news...'
                                }
                                style={{ minHeight: 72 }}
                                className="wp-lead-input"
                                rows={2}
                                maxLength={1000}
                                aria-label="Лид / Краткое описание"
                            />
                            {(fieldError('summary') ??
                                fieldError(`summary.${lang}`)) && (
                                <div className="wp-field-error">
                                    {fieldError('summary') ??
                                        fieldError(`summary.${lang}`)}
                                </div>
                            )}
                        </div>

                        {/* Rich Editor body */}
                        <div className="wp-body-wrapper">
                            <RichEditor
                                key={lang}
                                variant="article"
                                gallery
                                galleryContext={galleryContext}
                                onGalleryClick={() => setGalleryPicker(true)}
                                value={data.body[lang]}
                                onChange={(html) =>
                                    setLocaleField('body', html)
                                }
                                onActiveBlockChange={handleActiveBlockChange}
                                placeholder="Нажмите «/» для выбора блока или начните писать..."
                            />
                            {(fieldError('body') ??
                                fieldError(`body.${lang}`)) && (
                                <div className="wp-field-error">
                                    {fieldError('body') ??
                                        fieldError(`body.${lang}`)}
                                </div>
                            )}
                        </div>
                    </div>
                </main>

                {/* --------------------------------- Inspector Sidebar */}
                <NewsInspectorSidebar
                    isOpen={sidebarOpen}
                    onClose={() => setSidebarOpen(false)}
                    tab={sidebarTab}
                    setTab={setSidebarTab}
                    activeBlock={activeBlock}
                    data={data}
                    setData={setData}
                    fieldError={fieldError}
                    lang={lang}
                    reference={reference}
                    coverSrc={coverSrc}
                    coverFileRef={coverFileRef}
                    setCoverPicker={setCoverPicker}
                    setGalleryPicker={setGalleryPicker}
                    galleryPending={galleryPending}
                    news={news}
                    toggleTag={toggleTag}
                    setSeoField={setSeoField}
                />
            </div>

            <MediaPicker
                open={coverPicker}
                onClose={() => setCoverPicker(false)}
                onSelect={pickCoverFromLibrary}
            />

            <MediaPicker
                open={galleryPicker}
                onClose={() => setGalleryPicker(false)}
                multiple
                onSelectMany={addGalleryFromLibrary}
            />
        </EditorialFormShell>
    );
}
