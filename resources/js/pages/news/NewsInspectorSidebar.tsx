import {
    AlertTriangle,
    FileText,
    Heading,
    Image as ImageIcon,
    Images,
    Plus,
    Sliders,
    Table as TableIcon,
    Upload,
    Video,
    Wand2,
    X,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import type { RefObject } from 'react';
import { quickCategory } from '@/actions/App/Http/Controllers/Cms/TaxonomyController';
import { useCan } from '@/lib/auth';
import type { ContentLocale } from '@/lib/domain';
import { postJson } from '@/lib/http';
import { displayUrl, siteUrl, usePublicSiteUrl } from '@/lib/public-site';
import { slugify } from '@/lib/slugify';
import { AttachmentsField } from '@/ui/AttachmentsField';
import { Button } from '@/ui/Button';
import {
    Checkbox,
    DatePicker,
    Field,
    Input,
    Select,
    Textarea,
} from '@/ui/Field';
import { GalleryField } from '@/ui/GalleryField';
import { ReadinessWidget } from '@/ui/ReadinessWidget';
import type { ActiveBlockInfo } from '@/ui/RichEditor';

interface Option {
    value: number;
    label: string;
}

interface SeoFields {
    title: string;
    description: string;
}

interface Props {
    isOpen: boolean;
    onClose: () => void;
    tab: 'post' | 'block';
    setTab: (tab: 'post' | 'block') => void;
    activeBlock: ActiveBlockInfo | null;
    data: any;
    setData: (field: any, value: any) => void;
    fieldError: (key: string) => string | undefined;
    lang: ContentLocale;
    reference: {
        categories: Option[];
        tags: Option[];
        authors: Option[];
    };
    coverSrc: string | null;
    coverFileRef: RefObject<HTMLInputElement | null>;
    setCoverPicker: (open: boolean) => void;
    setGalleryPicker: (open: boolean) => void;
    galleryPending: { id: number; title: string; url: string }[];
    news: any | null;
    toggleTag: (id: number) => void;
    setSeoField: (field: keyof SeoFields, value: string) => void;
}

export function NewsInspectorSidebar({
    isOpen,
    onClose,
    tab,
    setTab,
    activeBlock,
    data,
    setData,
    fieldError,
    lang,
    reference,
    coverSrc,
    coverFileRef,
    setCoverPicker,
    setGalleryPicker,
    galleryPending,
    news,
    toggleTag,
    setSeoField,
}: Props) {
    const can = useCan();
    const publicSiteUrl = usePublicSiteUrl();
    const [addedCategories, setAddedCategories] = useState<Option[]>([]);
    const [showAddCat, setShowAddCat] = useState(false);
    const [newCatName, setNewCatName] = useState('');
    const [isCreatingCat, setIsCreatingCat] = useState(false);
    const [catError, setCatError] = useState<string | null>(null);

    const categories: Option[] = useMemo(
        () => [...reference.categories, ...addedCategories],
        [reference.categories, addedCategories],
    );

    const handleCreateCategory = async () => {
        if (!newCatName.trim()) {
            return;
        }

        setIsCreatingCat(true);
        setCatError(null);

        try {
            const created = await postJson<{ id: number; name: string }>(
                quickCategory.url(),
                { name: newCatName.trim() },
            );
            const newCat: Option = {
                value: created.id,
                label: created.name,
            };

            setAddedCategories((prev) => [...prev, newCat]);
            setData('category_id', created.id);
            setNewCatName('');
            setShowAddCat(false);
        } catch (e: unknown) {
            const err = e as { message?: string };
            setCatError(err.message ?? 'Не удалось создать рубрику');
        } finally {
            setIsCreatingCat(false);
        }
    };

    if (!isOpen) {
        return null;
    }

    const hasSpecialBlock = activeBlock && activeBlock.type !== 'paragraph';

    const handleAutoSlug = () => {
        const source =
            data.title[lang]?.trim() ||
            data.title.ru?.trim() ||
            data.title.tg?.trim() ||
            '';

        if (source) {
            setData('slug', slugify(source));
        }
    };

    // Оценка готовности материала (Traffic light score)
    const titleText = data.title[lang]?.trim() || '';
    const hasTitle = titleText.length > 0;

    const bodyHtml = data.body[lang] || '';
    const bodyText = bodyHtml
        .replace(/<[^>]*>/g, ' ')
        .replace(/\s+/g, ' ')
        .trim();
    const wordCount = bodyText ? bodyText.split(' ').length : 0;
    const hasBody = wordCount >= 20;

    const hasCover = Boolean(coverSrc);
    const hasCoverAlt = Boolean(data.cover_alt?.trim());
    const hasCategory = Boolean(data.category_id);
    const hasSummary = Boolean(data.summary[lang]?.trim());
    const hasBilingual = Boolean(
        (data.title.tg?.trim() && data.title.ru?.trim()) ||
        (lang === 'en' && data.title.en?.trim()),
    );

    let score = 0;

    if (hasTitle) {
        score += 20;
    }

    if (hasBody) {
        score += 25;
    }

    if (hasCover) {
        score += 15;
    }

    if (hasCoverAlt) {
        score += 10;
    }

    if (hasCategory) {
        score += 10;
    }

    if (hasSummary) {
        score += 10;
    }

    if (hasBilingual) {
        score += 10;
    }

    const readinessItems = [
        {
            id: 'title',
            label: `Заголовок (${lang.toUpperCase()})`,
            done: hasTitle,
        },
        {
            id: 'body',
            label: `Текст (${wordCount} сл., мин. 20)`,
            done: hasBody,
        },
        {
            id: 'cover',
            label: 'Изображение записи',
            done: hasCover,
        },
        {
            id: 'cover_alt',
            label: 'Alt-текст обложки',
            done: hasCoverAlt,
        },
        {
            id: 'category',
            label: 'Рубрика выбрана',
            done: hasCategory,
        },
        {
            id: 'summary',
            label: `Лид / анонс (${lang.toUpperCase()})`,
            done: hasSummary,
        },
        {
            id: 'bilingual',
            label: 'Заполнено на TG и RU',
            done: hasBilingual,
        },
    ];

    const permalinkPrefix = displayUrl(siteUrl(publicSiteUrl, '/news/', lang));

    return (
        <aside className="wp-inspector" aria-label="Панель настроек">
            <div className="wp-inspector-header">
                <div className="wp-inspector-tabs" role="tablist">
                    <button
                        type="button"
                        role="tab"
                        aria-selected={tab === 'post'}
                        className={`wp-inspector-tab ${tab === 'post' ? 'is-active' : ''}`}
                        onClick={() => setTab('post')}
                    >
                        <FileText size={15} />
                        <span>Запись</span>
                    </button>
                    <button
                        type="button"
                        role="tab"
                        aria-selected={tab === 'block'}
                        className={`wp-inspector-tab ${tab === 'block' ? 'is-active' : ''}`}
                        onClick={() => setTab('block')}
                    >
                        <Sliders size={15} />
                        <span>
                            Блок{' '}
                            {hasSpecialBlock && (
                                <span className="wp-badge-dot" />
                            )}
                        </span>
                    </button>
                </div>
                <button
                    type="button"
                    className="wp-inspector-close"
                    title="Скрыть панель"
                    aria-label="Скрыть панель"
                    onClick={onClose}
                >
                    <X size={16} strokeWidth={1.75} />
                </button>
            </div>

            <div className="wp-inspector-body">
                {tab === 'post' ? (
                    <div className="wp-inspector-sections">
                        {/* 0. Оценка готовности (WordPress-like Traffic Light / Checklist) */}
                        <ReadinessWidget score={score} items={readinessItems} />

                        {/* 1. Публикация и видимость */}
                        <section className="wp-inspector-section">
                            <h4 className="wp-inspector-section-title">
                                <span>Публикация</span>
                            </h4>
                            <div className="wp-inspector-section-content">
                                <Field
                                    label="Адрес (slug)"
                                    htmlFor="news-slug"
                                    hint="Генерируется автоматически из заголовка."
                                    error={fieldError('slug')}
                                >
                                    <div style={{ display: 'flex', gap: 6 }}>
                                        <Input
                                            id="news-slug"
                                            value={data.slug}
                                            onChange={(e) =>
                                                setData('slug', e.target.value)
                                            }
                                            hasError={!!fieldError('slug')}
                                            placeholder="naprimer-soobshchenie-2026"
                                            style={{ flex: 1 }}
                                        />
                                        <Button
                                            type="button"
                                            variant="secondary"
                                            size="sm"
                                            onClick={handleAutoSlug}
                                            title="Сгенерировать слаг из заголовка"
                                            icon={<Wand2 size={13} />}
                                        >
                                            Авто
                                        </Button>
                                    </div>
                                </Field>

                                <div className="wp-permalink-preview">
                                    <span className="wp-permalink-prefix">
                                        {permalinkPrefix}
                                    </span>
                                    <span className="wp-permalink-slug">
                                        {data.slug || 'novost-slug'}
                                    </span>
                                </div>

                                <Field
                                    label="Запланировать выход"
                                    htmlFor="news-scheduled-at"
                                >
                                    <DatePicker
                                        id="news-scheduled-at"
                                        withTime
                                        value={data.scheduled_at}
                                        onChange={(e) =>
                                            setData(
                                                'scheduled_at',
                                                e.target.value,
                                            )
                                        }
                                    />
                                </Field>

                                <div className="wp-checkbox-group">
                                    <Checkbox
                                        label="Закрепить вверху ленты"
                                        checked={data.is_pinned}
                                        onChange={(e) =>
                                            setData(
                                                'is_pinned',
                                                e.target.checked,
                                            )
                                        }
                                    />
                                    <Checkbox
                                        label="Показывать на главной странице"
                                        checked={data.show_on_home}
                                        onChange={(e) =>
                                            setData(
                                                'show_on_home',
                                                e.target.checked,
                                            )
                                        }
                                    />
                                </div>
                            </div>
                        </section>

                        {/* 2. Изображение записи (Главная обложка) */}
                        <section className="wp-inspector-section">
                            <h4 className="wp-inspector-section-title">
                                <span>Изображение записи</span>
                            </h4>
                            <div className="wp-inspector-section-content">
                                {coverSrc ? (
                                    <div className="wp-cover-preview-card">
                                        <img
                                            src={coverSrc}
                                            alt={
                                                data.cover_alt ||
                                                'Превью обложки'
                                            }
                                            className="wp-cover-img"
                                        />
                                        {news?.cover_url && (
                                            <Checkbox
                                                label="Удалить обложку"
                                                checked={data.cover_remove}
                                                onChange={(e) =>
                                                    setData(
                                                        'cover_remove',
                                                        e.target.checked,
                                                    )
                                                }
                                                className="mt-2"
                                            />
                                        )}
                                    </div>
                                ) : (
                                    <div
                                        className="wp-cover-placeholder"
                                        onClick={() => setCoverPicker(true)}
                                    >
                                        <ImageIcon
                                            size={28}
                                            strokeWidth={1.5}
                                        />
                                        <span>
                                            Установить изображение записи
                                        </span>
                                    </div>
                                )}

                                <input
                                    ref={coverFileRef as any}
                                    type="file"
                                    accept="image/png,image/jpeg,image/webp"
                                    hidden
                                    onChange={(e) => {
                                        const file =
                                            e.target.files?.[0] ?? null;

                                        if (file) {
                                            setData('cover', file);
                                            setData('cover_media_id', null);
                                            setData('cover_remove', false);
                                        }

                                        e.target.value = '';
                                    }}
                                />

                                <div className="wp-btn-row mt-2">
                                    <Button
                                        variant="secondary"
                                        size="sm"
                                        icon={<Upload size={14} />}
                                        onClick={() =>
                                            coverFileRef.current?.click()
                                        }
                                    >
                                        Загрузить
                                    </Button>
                                    <Button
                                        variant="secondary"
                                        size="sm"
                                        icon={<Images size={14} />}
                                        onClick={() => setCoverPicker(true)}
                                    >
                                        Медиатека
                                    </Button>
                                </div>

                                {fieldError('cover') && (
                                    <div className="wp-field-error">
                                        {fieldError('cover')}
                                    </div>
                                )}

                                <Field
                                    label="Alt-текст обложки"
                                    className="mt-3"
                                >
                                    <Input
                                        value={data.cover_alt}
                                        onChange={(e) =>
                                            setData('cover_alt', e.target.value)
                                        }
                                        placeholder="Описание для незрячих читателей"
                                    />
                                </Field>

                                <Field
                                    label="Подпись к фото"
                                    hint="Отображается под снимком в материале."
                                    className="mt-2"
                                >
                                    <Input
                                        value={data.cover_caption}
                                        onChange={(e) =>
                                            setData(
                                                'cover_caption',
                                                e.target.value,
                                            )
                                        }
                                        placeholder="Например: Фото пресс-службы КЧС"
                                        maxLength={500}
                                    />
                                </Field>
                            </div>
                        </section>

                        {/* 3. Рубрика и метки */}
                        <section className="wp-inspector-section">
                            <h4 className="wp-inspector-section-title">
                                <span>Рубрика и метки</span>
                            </h4>
                            <div className="wp-inspector-section-content">
                                <Field
                                    label="Рубрика (категория)"
                                    htmlFor="news-category"
                                    error={fieldError('category_id')}
                                >
                                    <Select
                                        id="news-category"
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
                                        placeholder="Без рубрики"
                                        options={categories.map((c) => ({
                                            value: c.value,
                                            label: c.label,
                                        }))}
                                    />
                                </Field>

                                {can('taxonomy.create') && (
                                    <div className="wp-quick-category">
                                        {!showAddCat ? (
                                            <button
                                                type="button"
                                                className="wp-quick-category-toggle"
                                                onClick={() =>
                                                    setShowAddCat(true)
                                                }
                                            >
                                                <Plus
                                                    size={13}
                                                    strokeWidth={2}
                                                />
                                                <span>
                                                    + Добавить новую рубрику
                                                </span>
                                            </button>
                                        ) : (
                                            <div className="wp-quick-category-form">
                                                <Input
                                                    value={newCatName}
                                                    onChange={(e) =>
                                                        setNewCatName(
                                                            e.target.value,
                                                        )
                                                    }
                                                    placeholder="Новая рубрика"
                                                    disabled={isCreatingCat}
                                                    onKeyDown={(e) => {
                                                        if (e.key === 'Enter') {
                                                            e.preventDefault();
                                                            handleCreateCategory();
                                                        }
                                                    }}
                                                />
                                                {catError && (
                                                    <div className="wp-field-error text-xs">
                                                        {catError}
                                                    </div>
                                                )}
                                                <div className="wp-btn-row">
                                                    <Button
                                                        type="button"
                                                        size="sm"
                                                        variant="secondary"
                                                        disabled={
                                                            isCreatingCat ||
                                                            !newCatName.trim()
                                                        }
                                                        onClick={
                                                            handleCreateCategory
                                                        }
                                                    >
                                                        {isCreatingCat
                                                            ? 'Сохранение…'
                                                            : 'Добавить'}
                                                    </Button>
                                                    <Button
                                                        type="button"
                                                        size="sm"
                                                        variant="ghost"
                                                        onClick={() => {
                                                            setShowAddCat(
                                                                false,
                                                            );
                                                            setNewCatName('');
                                                            setCatError(null);
                                                        }}
                                                    >
                                                        Отмена
                                                    </Button>
                                                </div>
                                            </div>
                                        )}
                                    </div>
                                )}

                                {reference.tags.length > 0 && (
                                    <Field
                                        label="Метки (теги)"
                                        className="mt-3"
                                    >
                                        <div className="wp-tags-cloud">
                                            {reference.tags.map((t) => {
                                                const checked =
                                                    data.tags.includes(t.value);

                                                return (
                                                    <button
                                                        key={t.value}
                                                        type="button"
                                                        className={`wp-tag-chip ${checked ? 'is-active' : ''}`}
                                                        onClick={() =>
                                                            toggleTag(t.value)
                                                        }
                                                    >
                                                        {t.label}
                                                    </button>
                                                );
                                            })}
                                        </div>
                                    </Field>
                                )}
                            </div>
                        </section>

                        {/* 4. Фотогалерея */}
                        <section className="wp-inspector-section">
                            <h4 className="wp-inspector-section-title">
                                <span>Фотогалерея события</span>
                            </h4>
                            <div className="wp-inspector-section-content">
                                <GalleryField
                                    existing={news?.gallery ?? []}
                                    addedFiles={data.gallery}
                                    addedLibrary={galleryPending}
                                    removed={data.gallery_remove}
                                    error={
                                        fieldError('gallery') ??
                                        fieldError('gallery_media_ids')
                                    }
                                    onAddFiles={(files) =>
                                        setData('gallery', [
                                            ...data.gallery,
                                            ...files,
                                        ])
                                    }
                                    onToggleRemove={(id) =>
                                        setData(
                                            'gallery_remove',
                                            data.gallery_remove.includes(id)
                                                ? data.gallery_remove.filter(
                                                      (x: number) => x !== id,
                                                  )
                                                : [...data.gallery_remove, id],
                                        )
                                    }
                                    onOpenPicker={() => setGalleryPicker(true)}
                                />
                            </div>
                        </section>

                        {/* 5. Официальные вложения */}
                        <section className="wp-inspector-section">
                            <h4 className="wp-inspector-section-title">
                                <span>Документы и вложения</span>
                            </h4>
                            <div className="wp-inspector-section-content">
                                <AttachmentsField
                                    existing={news?.attachments ?? []}
                                    added={data.attachments}
                                    removed={data.attachments_remove}
                                    error={fieldError('attachments')}
                                    onAdd={(files) =>
                                        setData('attachments', [
                                            ...data.attachments,
                                            ...files,
                                        ])
                                    }
                                    onToggleRemove={(id) =>
                                        setData(
                                            'attachments_remove',
                                            data.attachments_remove.includes(id)
                                                ? data.attachments_remove.filter(
                                                      (x: number) => x !== id,
                                                  )
                                                : [
                                                      ...data.attachments_remove,
                                                      id,
                                                  ],
                                        )
                                    }
                                />
                            </div>
                        </section>

                        {/* 6. SEO */}
                        <section className="wp-inspector-section">
                            <h4 className="wp-inspector-section-title">
                                <span>Поисковая оптимизация (SEO)</span>
                            </h4>
                            <div className="wp-inspector-section-content">
                                <div className="wp-seo-preview-card mb-3">
                                    <div className="wp-seo-preview-url">
                                        {`${permalinkPrefix}${data.slug || 'novost'}`
                                            .split('/')
                                            .filter(Boolean)
                                            .join(' › ')}
                                    </div>
                                    <div className="wp-seo-preview-title">
                                        {data.seo[lang].title.trim() ||
                                            data.title[lang]?.trim() ||
                                            'Заголовок новости — КЧС Таджикистана'}
                                    </div>
                                    <div className="wp-seo-preview-desc">
                                        {data.seo[lang].description.trim() ||
                                            data.summary[lang]?.trim() ||
                                            'Краткий анонс для поисковой выдачи Google и Яндекс будет показан здесь...'}
                                    </div>
                                </div>

                                <Field
                                    label="SEO-заголовок"
                                    htmlFor={`news-seo-title-${lang}`}
                                    error={fieldError(`seo.${lang}.title`)}
                                    hint={`${data.seo[lang].title.length} / 60 знаков`}
                                >
                                    <Input
                                        id={`news-seo-title-${lang}`}
                                        value={data.seo[lang].title}
                                        onChange={(e) =>
                                            setSeoField('title', e.target.value)
                                        }
                                        maxLength={255}
                                        placeholder={
                                            data.title[lang] ||
                                            'Заголовок для поисковиков'
                                        }
                                    />
                                </Field>

                                <Field
                                    label="SEO-описание (Сниппет)"
                                    htmlFor={`news-seo-description-${lang}`}
                                    error={fieldError(
                                        `seo.${lang}.description`,
                                    )}
                                    hint={`${data.seo[lang].description.length} / 160 знаков`}
                                    className="mt-3"
                                >
                                    <Textarea
                                        id={`news-seo-description-${lang}`}
                                        value={data.seo[lang].description}
                                        onChange={(e) =>
                                            setSeoField(
                                                'description',
                                                e.target.value,
                                            )
                                        }
                                        style={{ minHeight: 65 }}
                                        maxLength={500}
                                        placeholder={
                                            data.summary[lang] ||
                                            'Краткий анонс для поисковой выдачи'
                                        }
                                    />
                                </Field>
                            </div>
                        </section>
                    </div>
                ) : (
                    /* Вкладка «Блок» */
                    <div className="wp-inspector-sections">
                        {activeBlock?.type === 'image' && (
                            <section className="wp-inspector-section">
                                <div className="wp-block-header">
                                    <ImageIcon
                                        size={18}
                                        className="text-blue-500"
                                    />
                                    <strong>Изображение в тексте</strong>
                                </div>
                                <div className="wp-inspector-section-content">
                                    <p className="text-xs text-muted">
                                        Выравнивание, кадрирование и подпись
                                        настраиваются прямо на самом фото в
                                        тексте или кнопками ниже.
                                    </p>
                                    <div className="mt-3">
                                        <span className="wp-section-sublabel">
                                            Alt-текст
                                        </span>
                                        <p className="mt-1 text-xs text-secondary">
                                            {(activeBlock.attrs
                                                ?.alt as string) ||
                                                '— (не указан)'}
                                        </p>
                                    </div>
                                    <div className="mt-2">
                                        <span className="wp-section-sublabel">
                                            Подпись
                                        </span>
                                        <p className="mt-1 text-xs text-secondary">
                                            {(activeBlock.attrs
                                                ?.caption as string) ||
                                                '— (без подписи)'}
                                        </p>
                                    </div>
                                </div>
                            </section>
                        )}

                        {activeBlock?.type === 'callout' && (
                            <section className="wp-inspector-section">
                                <div className="wp-block-header">
                                    <AlertTriangle
                                        size={18}
                                        className="text-amber-500"
                                    />
                                    <strong>Врезка КЧС / Сообщение</strong>
                                </div>
                                <div className="wp-inspector-section-content">
                                    <p className="text-xs text-muted">
                                        Блок используется для экстренных
                                        сообщений, штормовых предупреждений и
                                        важных цитат руководства.
                                    </p>
                                    <div className="mt-3">
                                        <span className="wp-section-sublabel">
                                            Текущий тип:
                                        </span>
                                        <span className="wp-pill-label mt-1 inline-block font-medium">
                                            {activeBlock.attrs?.type ===
                                            'warning'
                                                ? 'Предупреждение КЧС'
                                                : activeBlock.attrs?.type ===
                                                    'info'
                                                  ? 'Важная информация'
                                                  : 'Официальная цитата'}
                                        </span>
                                    </div>
                                </div>
                            </section>
                        )}

                        {activeBlock?.type === 'table' && (
                            <section className="wp-inspector-section">
                                <div className="wp-block-header">
                                    <TableIcon
                                        size={18}
                                        className="text-emerald-500"
                                    />
                                    <strong>Таблица данных</strong>
                                </div>
                                <div className="wp-inspector-section-content">
                                    <p className="text-xs text-muted">
                                        Таблица активна. Для добавления или
                                        удаления строк и столбцов используйте
                                        панель управления таблицей над текстом.
                                    </p>
                                </div>
                            </section>
                        )}

                        {activeBlock?.type === 'youtube' && (
                            <section className="wp-inspector-section">
                                <div className="wp-block-header">
                                    <Video size={18} className="text-red-500" />
                                    <strong>Видео YouTube</strong>
                                </div>
                                <div className="wp-inspector-section-content">
                                    <p className="text-xs text-muted">
                                        Видеоролик вставлен.
                                    </p>
                                </div>
                            </section>
                        )}

                        {activeBlock?.type === 'heading' && (
                            <section className="wp-inspector-section">
                                <div className="wp-block-header">
                                    <Heading
                                        size={18}
                                        className="text-purple-500"
                                    />
                                    <strong>
                                        Заголовок уровня H
                                        {String(activeBlock.attrs?.level || 2)}
                                    </strong>
                                </div>
                                <div className="wp-inspector-section-content">
                                    <p className="text-xs text-muted">
                                        Используйте H2 для разделов и H3 для
                                        подразделов.
                                    </p>
                                </div>
                            </section>
                        )}

                        {(!activeBlock ||
                            activeBlock.type === 'paragraph' ||
                            activeBlock.type === 'blockquote') && (
                            <div className="wp-inspector-empty">
                                <Sliders
                                    size={32}
                                    strokeWidth={1.5}
                                    className="text-muted"
                                />
                                <h5>Настройки блока</h5>
                                <p>
                                    Кликните на изображение, таблицу, врезку или
                                    видео в тексте, чтобы настроить параметры
                                    конкретного блока.
                                </p>
                            </div>
                        )}
                    </div>
                )}
            </div>
        </aside>
    );
}
