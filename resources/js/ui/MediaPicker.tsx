import { ImageOff, Pencil, Upload } from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';
import { getJson, postForm } from '@/lib/http';
import { Button } from './Button';
import { Input } from './Field';
import { ImageEditor } from './ImageEditor';
import { Modal } from './Overlay';

/** Изображение из медиабиблиотеки (форма из MediaController::present). */
export interface MediaItem {
    id: number;
    url: string;
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
    meta: { current_page: number; last_page: number; total: number };
}

interface Props {
    open: boolean;
    onClose: () => void;
    onSelect: (item: MediaItem) => void;
}

const ACCEPT = 'image/png,image/jpeg,image/webp,image/gif';

/**
 * Модальный выбор изображения из медиабиблиотеки: поиск, сетка миниатюр и
 * загрузка нового файла прямо из окна. Возвращает выбранный элемент в onSelect.
 */
export function MediaPicker({ open, onClose, onSelect }: Props) {
    const [items, setItems] = useState<MediaItem[]>([]);
    const [search, setSearch] = useState('');
    const [loading, setLoading] = useState(false);
    const [uploading, setUploading] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [editing, setEditing] = useState<MediaItem | null>(null);
    const fileRef = useRef<HTMLInputElement>(null);

    const load = useCallback(async (query: string) => {
        setLoading(true);
        setError(null);

        try {
            const url = query
                ? `/media/library?search=${encodeURIComponent(query)}`
                : '/media/library';
            const res = await getJson<LibraryResponse>(url);
            setItems(res.data);
        } catch (e) {
            setError((e as Error).message);
        } finally {
            setLoading(false);
        }
    }, []);

    // Загружаем при открытии и с дебаунсом при вводе поиска.
    useEffect(() => {
        if (!open) {
            return;
        }

        const id = setTimeout(() => void load(search), search ? 300 : 0);

        return () => clearTimeout(id);
    }, [open, search, load]);

    const upload = async (file: File) => {
        setUploading(true);
        setError(null);

        try {
            const form = new FormData();
            form.append('file', file);
            form.append('title', file.name);
            const res = await postForm<{ data: MediaItem }>(
                '/media/library',
                form,
            );
            setItems((prev) => [res.data, ...prev]);
            onSelect(res.data); // только что загруженное — сразу вставляем
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
                onClose={onClose}
                title="Медиабиблиотека"
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
                        <Button variant="ghost" onClick={onClose}>
                            Закрыть
                        </Button>
                    </>
                }
            >
                <div style={{ marginBottom: 14 }}>
                    <Input
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        placeholder="Поиск по имени файла…"
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
                                : 'В библиотеке пока нет изображений — загрузите первое.'}
                        </span>
                    </div>
                ) : (
                    <div className="media-picker-grid">
                        {items.map((item) => (
                            <div key={item.id} className="media-tile">
                                <button
                                    type="button"
                                    className="media-tile-main"
                                    title={item.name ?? item.file_name}
                                    onClick={() => onSelect(item)}
                                >
                                    <img src={item.url} alt={item.name ?? ''} />
                                    <span className="media-tile-name">
                                        {item.name ?? item.file_name}
                                    </span>
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
                        ))}
                    </div>
                )}
            </Modal>

            <ImageEditor
                open={editing !== null}
                source={editing}
                onClose={() => setEditing(null)}
                onSaved={(item) => {
                    setItems((prev) => [item, ...prev]);
                    setEditing(null);
                    onSelect(item);
                }}
            />
        </>
    );
}
