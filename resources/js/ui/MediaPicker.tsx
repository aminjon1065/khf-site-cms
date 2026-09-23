import { Check, ImageOff, Pencil, Upload } from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';
import MediaController from '@/actions/App/Http/Controllers/Cms/MediaController';
import { getJson, postForm } from '@/lib/http';
import { Button } from './Button';
import { Input, Select } from './Field';
import { ImageEditor } from './ImageEditor';
import { Modal } from './Overlay';

/** Изображение из медиабиблиотеки (форма из MediaController::present). */
export interface MediaItem {
    id: number;
    url: string;
    /** Корневой путь ("/storage/…") — для сохранения в rich-text. */
    path?: string | null;
    name: string | null;
    file_name: string;
    ext: string;
    size: string;
    srcset: string | null;
    alt: string | null;
    caption: string | null;
}

interface LibraryResponse {
    data: MediaItem[];
    meta: {
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
}

interface Props {
    open: boolean;
    onClose: () => void;
    /** Одиночный выбор (без multiple): клик по снимку сразу отдаёт его и закрывает окно. */
    onSelect?: (item: MediaItem) => void;
    /**
     * Режим мультивыбора (например, для фотогалереи): клик по снимку не
     * закрывает окно, а отмечает его; выбранные отдаются разом через
     * onSelectMany в порядке отметки.
     */
    multiple?: boolean;
    onSelectMany?: (items: MediaItem[]) => void;
}

const ACCEPT = 'image/png,image/jpeg,image/webp,image/gif';

const SORT_OPTIONS = [
    { value: 'newest', label: 'Сначала новые' },
    { value: 'oldest', label: 'Сначала старые' },
    { value: 'name', label: 'По имени файла' },
];

/**
 * Модальный выбор изображения из медиабиблиотеки: поиск, сетка миниатюр и
 * загрузка нового файла прямо из окна. Возвращает выбранный элемент в onSelect.
 */
export function MediaPicker({
    open,
    onClose,
    onSelect,
    multiple = false,
    onSelectMany,
}: Props) {
    const [items, setItems] = useState<MediaItem[]>([]);
    const [search, setSearch] = useState('');
    const [sort, setSort] = useState('newest');
    const [page, setPage] = useState(1);
    const [total, setTotal] = useState(0);
    const [lastPage, setLastPage] = useState(1);
    const [loading, setLoading] = useState(false);
    const [loadingMore, setLoadingMore] = useState(false);
    const [uploading, setUploading] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [editing, setEditing] = useState<MediaItem | null>(null);
    const [selectedMap, setSelectedMap] = useState<Map<number, MediaItem>>(
        new Map(),
    );
    const fileRef = useRef<HTMLInputElement>(null);

    // Каждое закрытие окна начинает выбор заново: забытая отметка с
    // прошлого раза попала бы в галерею без ведома редактора. Сброс — в
    // обработчике закрытия, а не в эффекте (каскадные ре-рендеры).
    const handleClose = () => {
        setSelectedMap(new Map());
        onClose();
    };

    const selectedItems = Array.from(selectedMap.values());
    const selectedIds = Array.from(selectedMap.keys());

    const toggleSelected = (item: MediaItem) => {
        setSelectedMap((prev) => {
            const next = new Map(prev);

            if (next.has(item.id)) {
                next.delete(item.id);
            } else {
                next.set(item.id, item);
            }

            return next;
        });
    };

    /**
     * Загружает страницу библиотеки. Первая заменяет сетку, последующие
     * дописываются к ней — выбирая картинку, редактор не должен терять из
     * виду то, что уже пролистал.
     */
    const load = useCallback(
        async (query: string, order: string, nextPage: number) => {
            const append = nextPage > 1;
            (append ? setLoadingMore : setLoading)(true);
            setError(null);

            try {
                const res = await getJson<LibraryResponse>(
                    MediaController.library.url({
                        query: {
                            ...(query ? { search: query } : {}),
                            sort: order,
                            page: String(nextPage),
                        },
                    }),
                );

                setItems((prev) =>
                    append ? [...prev, ...res.data] : res.data,
                );
                setPage(res.meta.current_page);
                setLastPage(res.meta.last_page);
                setTotal(res.meta.total);
            } catch (e) {
                setError((e as Error).message);
            } finally {
                (append ? setLoadingMore : setLoading)(false);
            }
        },
        [],
    );

    // Открытие окна, ввод в поиске и смена сортировки всегда возвращают к
    // первой странице: догружать «ещё» поверх другого запроса бессмысленно.
    useEffect(() => {
        if (!open) {
            return;
        }

        const id = setTimeout(
            () => void load(search, sort, 1),
            search ? 300 : 0,
        );

        return () => clearTimeout(id);
    }, [open, search, sort, load]);

    const upload = async (file: File) => {
        setUploading(true);
        setError(null);

        try {
            const form = new FormData();
            form.append('file', file);
            form.append('title', file.name);
            const res = await postForm<{ data: MediaItem }>(
                MediaController.upload.url(),
                form,
            );
            setItems((prev) => [res.data, ...prev]);
            setTotal((prev) => prev + 1);

            if (multiple) {
                // Загруженный файл сразу отмечается: окно не закрывается,
                // редактор видит его в сетке и добавляет вместе с остальными.
                setSelectedMap((prev) =>
                    new Map(prev).set(res.data.id, res.data),
                );
            } else {
                onSelect?.(res.data);
                onClose();
            }
        } catch (e) {
            setError((e as Error).message);
        } finally {
            setUploading(false);
        }
    };

    return (
        <>
            <Modal
                open={open}
                onClose={handleClose}
                title="Медиатека"
                width={720}
                footer={
                    <>
                        <input
                            ref={fileRef}
                            type="file"
                            accept={ACCEPT}
                            hidden
                            onChange={(e) => {
                                const file = e.target.files?.[0];

                                if (file) {
                                    void upload(file);
                                }

                                e.target.value = '';
                            }}
                        />
                        <Button
                            variant="secondary"
                            icon={<Upload size={15} strokeWidth={1.75} />}
                            loading={uploading}
                            onClick={() => fileRef.current?.click()}
                        >
                            Загрузить файл
                        </Button>
                        <div style={{ flex: 1 }} />
                        {multiple && (
                            <Button
                                variant="primary"
                                disabled={selectedItems.length === 0}
                                onClick={() => {
                                    onSelectMany?.(selectedItems);
                                    handleClose();
                                }}
                            >
                                Добавить выбранные ({selectedItems.length})
                            </Button>
                        )}
                        <Button variant="ghost" onClick={onClose}>
                            Закрыть
                        </Button>
                    </>
                }
            >
                <div
                    style={{
                        display: 'flex',
                        gap: 8,
                        alignItems: 'center',
                        marginBottom: 14,
                    }}
                >
                    <Input
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        placeholder="Поиск по имени, названию, описанию и подписи…"
                        style={{ flex: 1 }}
                    />
                    <Select
                        aria-label="Порядок файлов"
                        value={sort}
                        options={SORT_OPTIONS}
                        onChange={(e) => setSort(e.target.value)}
                        style={{ width: 'auto' }}
                    />
                </div>

                {error && (
                    <div
                        style={{
                            color: 'var(--danger)',
                            fontSize: 13,
                            marginBottom: 12,
                        }}
                    >
                        {error}
                    </div>
                )}

                {loading ? (
                    <div className="media-picker-empty">Загрузка…</div>
                ) : items.length === 0 ? (
                    <div className="media-picker-empty">
                        <ImageOff size={22} strokeWidth={1.5} />
                        <span>
                            {search
                                ? 'Ничего не найдено.'
                                : 'В медиатеке пока нет изображений — загрузите файл, чтобы вставить его в текст.'}
                        </span>
                        {!search && (
                            <Button
                                variant="secondary"
                                icon={<Upload size={15} strokeWidth={1.75} />}
                                loading={uploading}
                                onClick={() => fileRef.current?.click()}
                            >
                                Загрузить файл
                            </Button>
                        )}
                    </div>
                ) : (
                    <>
                        <div className="media-picker-grid">
                            {items.map((item) => {
                                const isSelected = selectedIds.includes(
                                    item.id,
                                );

                                return (
                                    <div
                                        key={item.id}
                                        className="media-tile"
                                        style={
                                            multiple && isSelected
                                                ? {
                                                      outline:
                                                          '2px solid var(--color-accent-solid)',
                                                      outlineOffset: -2,
                                                  }
                                                : undefined
                                        }
                                    >
                                        <button
                                            type="button"
                                            className="media-tile-main"
                                            title={item.name ?? item.file_name}
                                            onClick={() => {
                                                if (multiple) {
                                                    toggleSelected(item);
                                                } else {
                                                    onSelect?.(item);
                                                    onClose();
                                                }
                                            }}
                                        >
                                            <img
                                                src={item.url}
                                                alt={item.name ?? ''}
                                            />
                                            <span className="media-tile-name">
                                                {item.name ?? item.file_name}
                                            </span>
                                            {multiple && isSelected && (
                                                <span
                                                    style={{
                                                        position: 'absolute',
                                                        top: 6,
                                                        right: 6,
                                                        display: 'grid',
                                                        placeItems: 'center',
                                                        width: 22,
                                                        height: 22,
                                                        borderRadius: '50%',
                                                        background:
                                                            'var(--color-accent-solid)',
                                                        color: '#fff',
                                                    }}
                                                >
                                                    <Check
                                                        size={14}
                                                        strokeWidth={2.25}
                                                    />
                                                </span>
                                            )}
                                        </button>
                                        <button
                                            type="button"
                                            className="media-tile-edit"
                                            title="Редактировать"
                                            aria-label="Редактировать изображение"
                                            onClick={() => setEditing(item)}
                                        >
                                            <Pencil size={13} />
                                        </button>
                                    </div>
                                );
                            })}
                        </div>

                        <div className="media-picker-more">
                            <span>
                                Показано {items.length} из {total}
                            </span>
                            {page < lastPage && (
                                <Button
                                    variant="secondary"
                                    loading={loadingMore}
                                    onClick={() =>
                                        void load(search, sort, page + 1)
                                    }
                                >
                                    Показать ещё
                                </Button>
                            )}
                        </div>
                    </>
                )}
            </Modal>

            <ImageEditor
                open={editing !== null}
                source={editing}
                onClose={() => setEditing(null)}
                onSaved={(item) => {
                    setItems((prev) => [item, ...prev]);
                    setEditing(null);

                    if (multiple) {
                        // Отредактированный снимок — как загруженный: отметить,
                        // а не закрывать окно с недосмотренной подборкой.
                        setSelectedMap((prev) =>
                            new Map(prev).set(item.id, item),
                        );
                    } else {
                        onSelect?.(item);
                    }
                }}
            />
        </>
    );
}
