import type { ReactNodeViewProps } from '@tiptap/react';
import { NodeViewWrapper } from '@tiptap/react';
import {
    ChevronLeft,
    ChevronRight,
    GripVertical,
    Images,
    LayoutGrid,
    Plus,
    RotateCcw,
    Trash2,
    Upload,
    X,
} from 'lucide-react';
import { useCallback, useRef, useState } from 'react';
import { useUploadLimits } from '@/hooks/use-upload-limits';
import { MEDIA_LOCKED_NOTE } from '@/lib/domain';
import { acceptOf, uploadProblem } from '@/lib/uploads';
import { cn } from '@/lib/utils';
import { Button } from '@/ui/Button';
import { MediaPicker } from '@/ui/MediaPicker';
import type { MediaItem } from '@/ui/MediaPicker';
import { useRichGallery } from './rich-gallery-context';
import type { RichGalleryItem } from './rich-gallery-context';
import { useToast } from './Toast';

export function RichGalleryView({
    node,
    selected,
    deleteNode,
    updateAttributes,
}: ReactNodeViewProps) {
    const galleryCtx = useRichGallery();
    const locked = galleryCtx?.locked ?? false;
    const photoLimit = useUploadLimits().image;
    const toast = useToast();
    const [pickerOpen, setPickerOpen] = useState(false);
    const [isDraggingOver, setIsDraggingOver] = useState(false);
    const [viewMode, setViewMode] = useState<'carousel' | 'grid'>('carousel');
    const [activeIndex, setActiveIndex] = useState(0);
    const fileInputRef = useRef<HTMLInputElement>(null);

    // Items source: form context if available, otherwise local node attributes
    const localItems = (node.attrs.images as RichGalleryItem[]) ?? [];
    const items =
        galleryCtx && galleryCtx.items.length > 0
            ? galleryCtx.items
            : localItems.length > 0
              ? localItems
              : (galleryCtx?.items ?? []);

    const activeCount = items.filter((it) => !it.isRemoved).length;
    const safeIndex =
        items.length === 0
            ? 0
            : Math.max(0, Math.min(activeIndex, items.length - 1));
    const currentItem = items[safeIndex];

    const nextSlide = useCallback(() => {
        setActiveIndex((idx) => Math.min(items.length - 1, idx + 1));
    }, [items.length]);

    const prevSlide = useCallback(() => {
        setActiveIndex((idx) => Math.max(0, idx - 1));
    }, []);

    const handleAddFiles = (files: FileList | File[] | null) => {
        if (!files || files.length === 0) {
            return;
        }

        // What the server won't take is turned away before the upload.
        const fileArr = Array.from(files).filter((file) => {
            const reason = uploadProblem(file, photoLimit, 'image');

            if (reason !== null) {
                toast(reason, 'error');
            }

            return reason === null;
        });

        if (fileArr.length === 0) {
            return;
        }

        if (galleryCtx?.onAddFiles) {
            galleryCtx.onAddFiles(fileArr);
        }

        const nextLocal: RichGalleryItem[] = [
            ...localItems,
            ...fileArr.map((f, i) => ({
                id: `local-${Date.now()}-${i}`,
                title: f.name,
                url: URL.createObjectURL(f),
                isPendingFile: true,
            })),
        ];
        updateAttributes({ images: nextLocal });
        setActiveIndex(items.length);
    };

    const handleSelectLibrary = (selectedMedia: MediaItem[]) => {
        if (selectedMedia.length === 0) {
            return;
        }

        if (galleryCtx?.onAddLibrary) {
            galleryCtx.onAddLibrary(selectedMedia);
        }

        const nextLocal: RichGalleryItem[] = [
            ...localItems,
            ...selectedMedia.map((m) => ({
                id: `lib-${m.id}`,
                title: m.name ?? m.caption ?? m.file_name ?? '',
                url: m.path ?? m.url,
                isPendingLibrary: true,
            })),
        ];
        updateAttributes({ images: nextLocal });

        // Set active index to first newly added item
        setActiveIndex(items.length);
        setPickerOpen(false);
    };

    const handleToggleRemove = (id: number | string) => {
        if (galleryCtx?.onToggleRemove) {
            galleryCtx.onToggleRemove(id);
        }

        const nextLocal = localItems.filter((it) => it.id !== id);
        updateAttributes({ images: nextLocal });
    };

    return (
        <NodeViewWrapper
            as="div"
            className={cn(
                're-gallery-view',
                selected && 'is-selected',
                isDraggingOver && 'is-drag-over',
            )}
            data-drag-handle=""
        >
            {/* Hidden native file input */}
            <input
                ref={fileInputRef}
                type="file"
                multiple
                accept={acceptOf(photoLimit)}
                style={{ display: 'none' }}
                onChange={(e) => {
                    handleAddFiles(e.target.files);
                    e.target.value = '';
                }}
            />

            {/* Gallery Block Header */}
            <div className="re-gallery-header">
                <div className="re-gallery-title-group">
                    <span
                        className="re-gallery-drag"
                        title="Перетащите блок для перемещения по тексту"
                    >
                        <GripVertical size={16} />
                    </span>
                    <span className="re-gallery-icon">
                        <Images size={17} />
                    </span>
                    <strong className="re-gallery-title">Фотогалерея</strong>
                    <span
                        className={cn(
                            're-gallery-count',
                            activeCount > 0
                                ? 're-gallery-count-active'
                                : 're-gallery-count-empty',
                        )}
                    >
                        {activeCount > 0
                            ? `${activeCount} ${activeCount === 1 ? 'снимок' : activeCount < 5 ? 'снимка' : 'снимков'}`
                            : 'нет кадров'}
                    </span>
                </div>

                <div className="re-gallery-actions" data-re-gallery-ui="true">
                    {/* View Mode Toggle when gallery has photos */}
                    {items.length > 0 && (
                        <div className="re-gallery-view-mode-toggle">
                            <button
                                type="button"
                                className={cn(
                                    're-gallery-view-mode-btn',
                                    viewMode === 'carousel' && 'is-active',
                                )}
                                onClick={() => setViewMode('carousel')}
                                title="Как карусель выглядит на сайте"
                            >
                                <Images size={13} />
                                <span>Карусель</span>
                            </button>
                            <button
                                type="button"
                                className={cn(
                                    're-gallery-view-mode-btn',
                                    viewMode === 'grid' && 'is-active',
                                )}
                                onClick={() => setViewMode('grid')}
                                title="Сетка всех миниатюр"
                            >
                                <LayoutGrid size={13} />
                                <span>Сетка</span>
                            </button>
                        </div>
                    )}

                    {!locked && (
                        <>
                            <Button
                                variant="secondary"
                                size="sm"
                                icon={<Upload size={13} />}
                                onClick={() => fileInputRef.current?.click()}
                                title="Загрузить фотографии с компьютера"
                            >
                                Загрузить
                            </Button>
                            <Button
                                variant="secondary"
                                size="sm"
                                icon={<Plus size={13} />}
                                onClick={() => setPickerOpen(true)}
                                title="Выбрать снимки из медиатеки сайта"
                            >
                                Из медиатеки
                            </Button>
                        </>
                    )}
                    <button
                        type="button"
                        className="re-gallery-btn-delete"
                        onClick={deleteNode}
                        title="Удалить блок фотогалереи из текста"
                    >
                        <Trash2 size={15} />
                    </button>
                </div>
            </div>

            {/* Gallery Content Area */}
            {items.length === 0 ? (
                /* Empty Placeholder State (WordPress Gutenberg style) */
                <div
                    className="re-gallery-empty"
                    data-re-gallery-ui="true"
                    onDragOver={(e) => {
                        e.preventDefault();
                        setIsDraggingOver(true);
                    }}
                    onDragLeave={(e) => {
                        e.preventDefault();
                        setIsDraggingOver(false);
                    }}
                    onDrop={(e) => {
                        e.preventDefault();
                        setIsDraggingOver(false);

                        if (!locked) {
                            handleAddFiles(e.dataTransfer.files);
                        }
                    }}
                >
                    <div className="re-gallery-empty-icon">
                        <Images size={36} strokeWidth={1.25} />
                    </div>
                    <div className="re-gallery-empty-title">
                        Фотогалерея материала
                    </div>
                    {locked ? (
                        <p className="re-gallery-empty-desc">
                            {MEDIA_LOCKED_NOTE}
                        </p>
                    ) : (
                        <>
                            <p className="re-gallery-empty-desc">
                                Перетащите фотографии сюда или добавьте снимки
                                кнопками ниже. На сайте они отобразятся
                                интерактивной каруселью.
                            </p>
                            <div className="re-gallery-empty-buttons">
                                <Button
                                    variant="primary"
                                    size="sm"
                                    icon={<Upload size={14} />}
                                    onClick={() =>
                                        fileInputRef.current?.click()
                                    }
                                >
                                    Загрузить снимки
                                </Button>
                                <Button
                                    variant="secondary"
                                    size="sm"
                                    icon={<Images size={14} />}
                                    onClick={() => setPickerOpen(true)}
                                >
                                    Выбрать из медиатеки
                                </Button>
                            </div>
                        </>
                    )}
                </div>
            ) : (
                /* Gallery Body */
                <div className="re-gallery-body" data-re-gallery-ui="true">
                    {viewMode === 'carousel' ? (
                        /* Carousel View (Matches Frontend UX) */
                        <div
                            className="re-gallery-carousel-wrap"
                            tabIndex={0}
                            role="region"
                            aria-label="Карусель фотогалереи"
                            onKeyDown={(e) => {
                                if (e.key === 'ArrowLeft') {
                                    e.preventDefault();
                                    prevSlide();
                                } else if (e.key === 'ArrowRight') {
                                    e.preventDefault();
                                    nextSlide();
                                }
                            }}
                        >
                            {/* Slide Stage */}
                            <div className="re-gallery-carousel-stage">
                                {currentItem && (
                                    <>
                                        <img
                                            src={currentItem.url}
                                            alt={currentItem.title || ''}
                                            className="re-gallery-carousel-img"
                                            loading="lazy"
                                        />

                                        {/* Source Badges */}
                                        {currentItem.isPendingLibrary && (
                                            <span className="re-gallery-carousel-badge is-lib">
                                                медиатека
                                            </span>
                                        )}
                                        {currentItem.isPendingFile && (
                                            <span className="re-gallery-carousel-badge is-file">
                                                новый файл
                                            </span>
                                        )}
                                        {currentItem.isExisting && (
                                            <span className="re-gallery-carousel-badge is-exist">
                                                сохранено
                                            </span>
                                        )}

                                        {/* Quick Actions (Remove / Restore) */}
                                        {!locked && (
                                            <div className="re-gallery-carousel-actions">
                                                {currentItem.isRemoved ? (
                                                    <button
                                                        type="button"
                                                        className="re-gallery-carousel-btn-action"
                                                        onClick={() =>
                                                            handleToggleRemove(
                                                                currentItem.id,
                                                            )
                                                        }
                                                        title="Восстановить снимок"
                                                    >
                                                        <RotateCcw size={16} />
                                                    </button>
                                                ) : (
                                                    <button
                                                        type="button"
                                                        className="re-gallery-carousel-btn-action"
                                                        onClick={() =>
                                                            handleToggleRemove(
                                                                currentItem.id,
                                                            )
                                                        }
                                                        title="Удалить этот снимок"
                                                    >
                                                        <X size={16} />
                                                    </button>
                                                )}
                                            </div>
                                        )}

                                        {/* Caption Overlay */}
                                        {currentItem.title && (
                                            <div
                                                className="re-gallery-carousel-caption"
                                                title={currentItem.title}
                                            >
                                                {currentItem.title}
                                            </div>
                                        )}

                                        {/* Removed Overlay */}
                                        {currentItem.isRemoved && (
                                            <div className="re-gallery-carousel-removed-overlay">
                                                <span className="text-sm font-semibold">
                                                    Снимок помечен на удаление
                                                </span>
                                                {!locked && (
                                                    <Button
                                                        variant="secondary"
                                                        size="sm"
                                                        icon={
                                                            <RotateCcw
                                                                size={14}
                                                            />
                                                        }
                                                        onClick={() =>
                                                            handleToggleRemove(
                                                                currentItem.id,
                                                            )
                                                        }
                                                    >
                                                        Восстановить
                                                    </Button>
                                                )}
                                            </div>
                                        )}
                                    </>
                                )}
                            </div>

                            {/* Carousel Controls */}
                            <div className="re-gallery-carousel-controls">
                                <div className="re-gallery-carousel-nav">
                                    <button
                                        type="button"
                                        className="re-gallery-carousel-nav-btn"
                                        disabled={safeIndex === 0}
                                        onClick={prevSlide}
                                        title="Предыдущий снимок (клавиша ←)"
                                        aria-label="Предыдущий снимок"
                                    >
                                        <ChevronLeft
                                            size={18}
                                            strokeWidth={2}
                                        />
                                    </button>
                                    <button
                                        type="button"
                                        className="re-gallery-carousel-nav-btn"
                                        disabled={safeIndex >= items.length - 1}
                                        onClick={nextSlide}
                                        title="Следующий снимок (клавиша →)"
                                        aria-label="Следующий снимок"
                                    >
                                        <ChevronRight
                                            size={18}
                                            strokeWidth={2}
                                        />
                                    </button>
                                    <span className="re-gallery-carousel-counter">
                                        {safeIndex + 1} / {items.length}
                                    </span>
                                </div>

                                <div className="text-xs text-[var(--color-neutral-500)]">
                                    Интерактивный слайдер как на сайте
                                </div>
                            </div>

                            {/* Thumbnail Navigation Strip */}
                            {items.length > 1 && (
                                <div
                                    className="re-gallery-strip"
                                    role="tablist"
                                    aria-label="Миниатюры слайдов"
                                >
                                    {items.map((item, idx) => (
                                        <button
                                            key={String(item.id)}
                                            type="button"
                                            className={cn(
                                                're-gallery-strip-thumb',
                                                idx === safeIndex &&
                                                    'is-active',
                                                item.isRemoved && 'is-removed',
                                            )}
                                            onClick={() => setActiveIndex(idx)}
                                            title={
                                                item.title || `Кадр ${idx + 1}`
                                            }
                                            role="tab"
                                            aria-selected={idx === safeIndex}
                                        >
                                            <img
                                                src={item.url}
                                                alt={item.title || ''}
                                                loading="lazy"
                                            />
                                        </button>
                                    ))}
                                </div>
                            )}
                        </div>
                    ) : (
                        /* Grid Mode */
                        <div className="re-gallery-grid">
                            {items.map((item) => {
                                const isRemoved = Boolean(item.isRemoved);

                                return (
                                    <div
                                        key={String(item.id)}
                                        className={cn(
                                            're-gallery-card',
                                            isRemoved && 'is-removed',
                                        )}
                                        title={item.title || 'Снимок галереи'}
                                    >
                                        <div className="re-gallery-thumb-wrapper">
                                            <img
                                                src={item.url}
                                                alt={item.title || ''}
                                                className="re-gallery-thumb"
                                                loading="lazy"
                                            />

                                            {/* Source Badges */}
                                            {item.isPendingLibrary && (
                                                <span className="re-gallery-card-badge is-lib">
                                                    медиатека
                                                </span>
                                            )}
                                            {item.isPendingFile && (
                                                <span className="re-gallery-card-badge is-file">
                                                    файл
                                                </span>
                                            )}
                                            {item.isExisting && (
                                                <span className="re-gallery-card-badge is-exist">
                                                    сохранено
                                                </span>
                                            )}

                                            {/* Hover Overlay & Action */}
                                            {!locked && (
                                                <div className="re-gallery-overlay">
                                                    {isRemoved ? (
                                                        <button
                                                            type="button"
                                                            className="re-gallery-restore-btn"
                                                            onClick={() =>
                                                                handleToggleRemove(
                                                                    item.id,
                                                                )
                                                            }
                                                            title="Вернуть снимок в галерею"
                                                        >
                                                            <RotateCcw
                                                                size={14}
                                                            />
                                                            <span>Вернуть</span>
                                                        </button>
                                                    ) : (
                                                        <button
                                                            type="button"
                                                            className="re-gallery-remove-btn"
                                                            onClick={() =>
                                                                handleToggleRemove(
                                                                    item.id,
                                                                )
                                                            }
                                                            title="Удалить этот снимок"
                                                        >
                                                            <X size={16} />
                                                        </button>
                                                    )}
                                                </div>
                                            )}

                                            {isRemoved && (
                                                <div className="re-gallery-removed-dim">
                                                    <span>Удалено</span>
                                                </div>
                                            )}
                                        </div>
                                        {item.title && (
                                            <div className="re-gallery-card-caption">
                                                {item.title}
                                            </div>
                                        )}
                                    </div>
                                );
                            })}
                        </div>
                    )}
                </div>
            )}

            {/* Footer Tip */}
            <div className="re-gallery-footer">
                <span>
                    💡 На сайте читатели увидят этот блок как карусель с
                    полноэкранным просмотром. Порядок снимков соответствует
                    порядку добавления.
                </span>
            </div>

            {/* Media Library Picker Modal with multi-select */}
            <MediaPicker
                open={pickerOpen}
                onClose={() => setPickerOpen(false)}
                multiple
                onSelectMany={handleSelectLibrary}
            />
        </NodeViewWrapper>
    );
}
