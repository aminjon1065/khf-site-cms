import { Head, router } from '@inertiajs/react';
import {
    Check,
    Copy,
    FileText,
    Focus,
    Link2,
    Pencil,
    RefreshCcw,
    RotateCcw,
    Trash2,
    Upload,
} from 'lucide-react';
import { useRef, useState } from 'react';
import {
    destroy,
    index,
    restore,
    retryConversions,
    store,
    update,
    usages,
} from '@/actions/App/Http/Controllers/Cms/MediaController';
import { useClipboard } from '@/hooks/use-clipboard';
import { useCan } from '@/lib/auth';
import { Tag } from '@/ui/Badge';
import { Blueprint } from '@/ui/Blueprint';
import { Button, IconButton } from '@/ui/Button';
import { Pagination } from '@/ui/DataTable';
import { Checkbox, Field, Input, Select, Textarea } from '@/ui/Field';
import { FilterBar, SearchInput } from '@/ui/Filters';
import { ConfirmDialog, Modal } from '@/ui/Overlay';
import { PageHeader } from '@/ui/PageHeader';

interface MediaItem {
    id: number;
    url: string;
    name: string;
    file_name: string;
    ext: string;
    mime: string;
    kind: 'image' | 'file';
    size: string;
    collection: string;
    usage: string;
    owned: boolean;
    title: string | null;
    alt: string | null;
    caption: string | null;
    is_decorative: boolean;
    focal_point: { x: number; y: number };
    trashed: boolean;
    uploaded_at: string | null;
    conversion_status: 'pending' | 'processing' | 'ready' | 'failed' | null;
    conversion_error: string | null;
}

interface Props {
    items: MediaItem[];
    meta: {
        from: number | null;
        to: number | null;
        total: number;
        per_page: number;
        prev: string | null;
        next: string | null;
    };
    filters: { kind: string; search: string; status: 'active' | 'trash' };
    stats: { total: number; images: number; library: number; trash: number };
}

interface MediaUsage {
    type: string;
    id: number;
    title: string;
    edit_url: string;
}

const KIND_OPTIONS = [
    { value: '', label: 'Все файлы' },
    { value: 'image', label: 'Изображения' },
    { value: 'file', label: 'Документы' },
];

const STATUS_OPTIONS = [
    { value: 'active', label: 'Активные' },
    { value: 'trash', label: 'Корзина' },
];

export default function MediaIndex({ items, meta, filters, stats }: Props) {
    const can = useCan();
    const [, copyToClipboard] = useClipboard();
    const fileInput = useRef<HTMLInputElement>(null);
    const [uploading, setUploading] = useState(false);
    const [uploadError, setUploadError] = useState<string | null>(null);
    const [copied, setCopied] = useState<number | null>(null);
    const [deleteTarget, setDeleteTarget] = useState<MediaItem | null>(null);
    const [processing, setProcessing] = useState(false);
    const [editTarget, setEditTarget] = useState<MediaItem | null>(null);
    const [editForm, setEditForm] = useState({
        name: '',
        alt: '',
        caption: '',
        is_decorative: false,
        focal_x: 0.5,
        focal_y: 0.5,
    });
    const [editSaving, setEditSaving] = useState(false);
    const [editError, setEditError] = useState<string | null>(null);
    const [retrying, setRetrying] = useState<number | null>(null);
    const [usageTarget, setUsageTarget] = useState<MediaItem | null>(null);
    const [usageItems, setUsageItems] = useState<MediaUsage[]>([]);
    const [usageLoading, setUsageLoading] = useState(false);
    const [usageError, setUsageError] = useState<string | null>(null);

    const openEdit = (item: MediaItem) => {
        setEditError(null);
        setEditForm({
            name: item.title ?? item.name ?? '',
            alt: item.alt ?? '',
            caption: item.caption ?? '',
            is_decorative: item.is_decorative,
            focal_x: item.focal_point.x,
            focal_y: item.focal_point.y,
        });
        setEditTarget(item);
    };

    const saveEdit = () => {
        if (!editTarget) {
            return;
        }

        setEditSaving(true);
        router.put(update.url(editTarget.id), editForm, {
            preserveScroll: true,
            onSuccess: () => setEditTarget(null),
            onError: (errors) =>
                setEditError(
                    errors.alt ??
                        errors.focal_x ??
                        errors.focal_y ??
                        'Не удалось сохранить метаданные.',
                ),
            onFinish: () => setEditSaving(false),
        });
    };

    const reload = (patch: Partial<Props['filters']>) => {
        router.get(
            index.url(),
            { ...filters, ...patch },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const pickFile = () => fileInput.current?.click();

    const onFilePicked = (e: React.ChangeEvent<HTMLInputElement>) => {
        const file = e.target.files?.[0];

        if (!file) {
            return;
        }

        const fd = new FormData();
        fd.append('file', file);
        setUploadError(null);
        setUploading(true);

        router.post(store.url(), fd, {
            forceFormData: true,
            preserveScroll: true,
            onError: (errs) =>
                setUploadError(errs.file ?? 'Не удалось загрузить файл.'),
            onFinish: () => {
                setUploading(false);

                if (fileInput.current) {
                    fileInput.current.value = '';
                }
            },
        });
    };

    const openUsages = async (item: MediaItem) => {
        setUsageTarget(item);
        setUsageItems([]);
        setUsageError(null);
        setUsageLoading(true);

        try {
            const response = await fetch(usages.url(item.id), {
                credentials: 'same-origin',
                headers: { Accept: 'application/json' },
            });

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }

            const payload = (await response.json()) as { data: MediaUsage[] };
            setUsageItems(payload.data);
        } catch {
            setUsageError('Не удалось загрузить список использований.');
        } finally {
            setUsageLoading(false);
        }
    };

    const copyUrl = async (item: MediaItem) => {
        const ok = await copyToClipboard(item.url);

        if (!ok) {
            return;
        }

        setCopied(item.id);
        window.setTimeout(() => setCopied(null), 1500);
    };

    return (
        <>
            <Head title="Медиабиблиотека" />
            <PageHeader
                title="Медиабиблиотека"
                subtitle={`Всего файлов: ${stats.total} · изображений: ${stats.images} · в библиотеке: ${stats.library} · в корзине: ${stats.trash}`}
                actions={
                    can('media.create') && (
                        <Button
                            variant="primary"
                            icon={<Upload size={16} strokeWidth={1.75} />}
                            loading={uploading}
                            onClick={pickFile}
                        >
                            Загрузить файл
                        </Button>
                    )
                }
            />

            <input
                ref={fileInput}
                type="file"
                hidden
                accept=".jpg,.jpeg,.png,.webp,.gif,.pdf,.doc,.docx,.xls,.xlsx"
                onChange={onFilePicked}
            />

            {uploadError && (
                <div
                    role="alert"
                    style={{
                        marginBottom: 14,
                        padding: '10px 14px',
                        borderRadius: 8,
                        fontSize: 13,
                        color: 'var(--color-danger-700, #b42318)',
                        background: 'var(--color-danger-50, #fef3f2)',
                        border: '1px solid var(--color-danger-200, #fecdca)',
                    }}
                >
                    {uploadError}
                </div>
            )}

            <FilterBar>
                <SearchInput
                    placeholder="Поиск по имени файла…"
                    defaultValue={filters.search}
                    onChange={(e) => reload({ search: e.target.value })}
                />
                <Select
                    aria-label="Фильтр файлов по типу"
                    value={filters.kind}
                    options={KIND_OPTIONS}
                    onChange={(e) => reload({ kind: e.target.value })}
                    style={{ width: 'auto' }}
                />
                <Select
                    value={filters.status}
                    options={STATUS_OPTIONS}
                    onChange={(e) =>
                        reload({
                            status: e.target.value as 'active' | 'trash',
                        })
                    }
                    style={{ width: 'auto' }}
                    aria-label="Статус файла"
                />
                <span
                    style={{
                        marginLeft: 'auto',
                        fontSize: 12.5,
                        color: 'var(--color-neutral-600)',
                    }}
                >
                    {items.length} из {meta.total}
                </span>
            </FilterBar>

            {items.length === 0 ? (
                <Blueprint style={{ padding: 48, textAlign: 'center' }}>
                    <p
                        style={{
                            margin: 0,
                            fontSize: 14,
                            color: 'var(--color-neutral-600)',
                        }}
                    >
                        Файлы не найдены.
                    </p>
                </Blueprint>
            ) : (
                <div
                    style={{
                        display: 'grid',
                        gridTemplateColumns:
                            'repeat(auto-fill, minmax(190px, 1fr))',
                        gap: 14,
                    }}
                >
                    {items.map((item) => (
                        <Blueprint
                            key={item.id}
                            style={{
                                padding: 0,
                                overflow: 'hidden',
                                display: 'flex',
                                flexDirection: 'column',
                            }}
                        >
                            <div
                                style={{
                                    height: 130,
                                    display: 'flex',
                                    alignItems: 'center',
                                    justifyContent: 'center',
                                    background: 'var(--color-neutral-100)',
                                    borderBottom:
                                        '1px solid var(--color-divider)',
                                    overflow: 'hidden',
                                }}
                            >
                                {item.kind === 'image' ? (
                                    <img
                                        src={item.url}
                                        alt={
                                            item.is_decorative
                                                ? ''
                                                : (item.alt ?? item.name)
                                        }
                                        style={{
                                            width: '100%',
                                            height: '100%',
                                            objectFit: 'cover',
                                            objectPosition: `${item.focal_point.x * 100}% ${item.focal_point.y * 100}%`,
                                        }}
                                    />
                                ) : (
                                    <div
                                        style={{
                                            display: 'flex',
                                            flexDirection: 'column',
                                            alignItems: 'center',
                                            gap: 6,
                                            color: 'var(--color-neutral-500)',
                                        }}
                                    >
                                        <FileText size={34} strokeWidth={1.4} />
                                        <span
                                            className="ui-mono"
                                            style={{ fontSize: 12 }}
                                        >
                                            {item.ext}
                                        </span>
                                    </div>
                                )}
                            </div>

                            <div
                                style={{
                                    padding: '10px 12px',
                                    display: 'flex',
                                    flexDirection: 'column',
                                    gap: 6,
                                    flex: 1,
                                }}
                            >
                                <span
                                    title={item.file_name}
                                    style={{
                                        fontSize: 12.5,
                                        fontWeight: 600,
                                        whiteSpace: 'nowrap',
                                        overflow: 'hidden',
                                        textOverflow: 'ellipsis',
                                    }}
                                >
                                    {item.file_name}
                                </span>
                                <div
                                    style={{
                                        display: 'flex',
                                        alignItems: 'center',
                                        gap: 6,
                                    }}
                                >
                                    <Tag
                                        tone={item.owned ? 'accent' : 'neutral'}
                                    >
                                        {item.trashed ? 'Корзина' : item.usage}
                                    </Tag>
                                    {item.kind === 'image' &&
                                        !item.alt &&
                                        !item.is_decorative && (
                                            <Tag tone="danger">Нет alt</Tag>
                                        )}
                                    {item.conversion_status && (
                                        <Tag
                                            tone={
                                                item.conversion_status ===
                                                'ready'
                                                    ? 'ok'
                                                    : item.conversion_status ===
                                                        'failed'
                                                      ? 'danger'
                                                      : 'info'
                                            }
                                        >
                                            {item.conversion_status === 'ready'
                                                ? 'Готово'
                                                : item.conversion_status ===
                                                    'failed'
                                                  ? 'Ошибка'
                                                  : 'Обрабатывается'}
                                        </Tag>
                                    )}
                                    <span
                                        style={{
                                            fontSize: 11.5,
                                            color: 'var(--color-neutral-500)',
                                        }}
                                    >
                                        {item.size}
                                    </span>
                                    <span style={{ flex: 1 }} />
                                    {item.conversion_status === 'failed' &&
                                        can('media.create') && (
                                            <IconButton
                                                label={
                                                    item.conversion_error
                                                        ? `Повторить обработку. Ошибка: ${item.conversion_error}`
                                                        : 'Повторить обработку'
                                                }
                                                variant="ghost"
                                                disabled={retrying === item.id}
                                                onClick={() => {
                                                    setRetrying(item.id);
                                                    router.post(
                                                        retryConversions.url(
                                                            item.id,
                                                        ),
                                                        {},
                                                        {
                                                            preserveScroll: true,
                                                            onFinish: () =>
                                                                setRetrying(
                                                                    null,
                                                                ),
                                                        },
                                                    );
                                                }}
                                            >
                                                <RefreshCcw
                                                    size={15}
                                                    strokeWidth={1.5}
                                                />
                                            </IconButton>
                                        )}
                                    <IconButton
                                        label="Где используется"
                                        variant="ghost"
                                        onClick={() => openUsages(item)}
                                    >
                                        <Link2 size={15} strokeWidth={1.5} />
                                    </IconButton>
                                    <IconButton
                                        label="Скопировать ссылку"
                                        variant="ghost"
                                        onClick={() => copyUrl(item)}
                                    >
                                        {copied === item.id ? (
                                            <Check
                                                size={15}
                                                strokeWidth={1.75}
                                                color="var(--color-success-600, #067647)"
                                            />
                                        ) : (
                                            <Copy size={15} strokeWidth={1.5} />
                                        )}
                                    </IconButton>
                                    {item.owned &&
                                        !item.trashed &&
                                        item.kind === 'image' &&
                                        can('media.edit') && (
                                            <IconButton
                                                label="Редактировать"
                                                variant="ghost"
                                                onClick={() => openEdit(item)}
                                            >
                                                <Pencil
                                                    size={15}
                                                    strokeWidth={1.5}
                                                />
                                            </IconButton>
                                        )}
                                    {item.owned &&
                                        item.trashed &&
                                        can('media.delete') && (
                                            <IconButton
                                                label="Восстановить"
                                                variant="ghost"
                                                onClick={() => {
                                                    setProcessing(true);
                                                    router.post(
                                                        restore.url(item.id),
                                                        {},
                                                        {
                                                            preserveScroll: true,
                                                            onFinish: () =>
                                                                setProcessing(
                                                                    false,
                                                                ),
                                                        },
                                                    );
                                                }}
                                            >
                                                <RotateCcw
                                                    size={15}
                                                    strokeWidth={1.5}
                                                />
                                            </IconButton>
                                        )}
                                    {item.owned &&
                                        !item.trashed &&
                                        can('media.delete') && (
                                            <IconButton
                                                label="Переместить в корзину"
                                                variant="ghost"
                                                onClick={() =>
                                                    setDeleteTarget(item)
                                                }
                                            >
                                                <Trash2
                                                    size={15}
                                                    strokeWidth={1.5}
                                                />
                                            </IconButton>
                                        )}
                                </div>
                            </div>
                        </Blueprint>
                    ))}
                </div>
            )}

            <Pagination
                from={meta.from ?? 0}
                to={meta.to ?? 0}
                total={meta.total}
                perPage={meta.per_page}
                onPrev={
                    meta.prev
                        ? () =>
                              router.visit(meta.prev!, {
                                  preserveState: true,
                                  preserveScroll: true,
                              })
                        : undefined
                }
                onNext={
                    meta.next
                        ? () =>
                              router.visit(meta.next!, {
                                  preserveState: true,
                                  preserveScroll: true,
                              })
                        : undefined
                }
            />

            <ConfirmDialog
                open={!!deleteTarget}
                onClose={() => setDeleteTarget(null)}
                loading={processing}
                title="Удалить файл?"
                body={
                    deleteTarget
                        ? `Файл «${deleteTarget.file_name}» переместится в корзину. Если он используется в материале, операция будет отменена.`
                        : ''
                }
                confirmLabel="В корзину"
                onConfirm={() => {
                    if (!deleteTarget) {
                        return;
                    }

                    setProcessing(true);
                    router.delete(destroy.url(deleteTarget.id), {
                        preserveScroll: true,
                        onFinish: () => {
                            setProcessing(false);
                            setDeleteTarget(null);
                        },
                    });
                }}
            />

            <Modal
                open={!!editTarget}
                onClose={() => setEditTarget(null)}
                title="Метаданные изображения"
                width={520}
                footer={
                    <>
                        <Button
                            variant="ghost"
                            onClick={() => setEditTarget(null)}
                        >
                            Отмена
                        </Button>
                        <Button
                            variant="primary"
                            loading={editSaving}
                            onClick={saveEdit}
                        >
                            Сохранить
                        </Button>
                    </>
                }
            >
                {editError && (
                    <div role="alert" className="ui-form-error-summary">
                        {editError}
                    </div>
                )}
                <Field label="Название">
                    <Input
                        value={editForm.name}
                        onChange={(e) =>
                            setEditForm({ ...editForm, name: e.target.value })
                        }
                        maxLength={255}
                        placeholder="Название файла"
                    />
                </Field>
                <Field
                    label="Alt-текст"
                    hint="Для доступности и SEO; подставляется при вставке картинки в текст."
                >
                    <Input
                        value={editForm.alt}
                        disabled={editForm.is_decorative}
                        onChange={(e) =>
                            setEditForm({ ...editForm, alt: e.target.value })
                        }
                        maxLength={255}
                        placeholder="Что изображено на картинке"
                    />
                </Field>
                <Checkbox
                    checked={editForm.is_decorative}
                    onChange={(event) =>
                        setEditForm({
                            ...editForm,
                            is_decorative: event.target.checked,
                            alt: event.target.checked ? '' : editForm.alt,
                        })
                    }
                    label="Декоративное изображение — alt будет пустым"
                />
                <Field
                    label="Подпись"
                    hint="Показывается под изображением при вставке в статью."
                >
                    <Textarea
                        value={editForm.caption}
                        onChange={(e) =>
                            setEditForm({
                                ...editForm,
                                caption: e.target.value,
                            })
                        }
                        maxLength={500}
                        style={{ minHeight: 60 }}
                    />
                </Field>
                {editTarget?.kind === 'image' && (
                    <Field
                        label="Фокус изображения"
                        hint="Нажмите на главный объект — эта точка останется в кадре на карточках разных размеров."
                    >
                        <button
                            type="button"
                            aria-label="Выбрать точку фокуса"
                            onClick={(event) => {
                                const bounds =
                                    event.currentTarget.getBoundingClientRect();
                                setEditForm({
                                    ...editForm,
                                    focal_x: Math.max(
                                        0,
                                        Math.min(
                                            1,
                                            (event.clientX - bounds.left) /
                                                bounds.width,
                                        ),
                                    ),
                                    focal_y: Math.max(
                                        0,
                                        Math.min(
                                            1,
                                            (event.clientY - bounds.top) /
                                                bounds.height,
                                        ),
                                    ),
                                });
                            }}
                            style={{
                                position: 'relative',
                                display: 'block',
                                width: '100%',
                                height: 220,
                                padding: 0,
                                overflow: 'hidden',
                                cursor: 'crosshair',
                                borderRadius: 8,
                                border: '1px solid var(--color-divider)',
                                background: 'var(--color-neutral-100)',
                            }}
                        >
                            <img
                                src={editTarget.url}
                                alt=""
                                style={{
                                    width: '100%',
                                    height: '100%',
                                    objectFit: 'cover',
                                    objectPosition: `${editForm.focal_x * 100}% ${editForm.focal_y * 100}%`,
                                }}
                            />
                            <span
                                aria-hidden="true"
                                style={{
                                    position: 'absolute',
                                    left: `${editForm.focal_x * 100}%`,
                                    top: `${editForm.focal_y * 100}%`,
                                    display: 'grid',
                                    width: 28,
                                    height: 28,
                                    placeItems: 'center',
                                    color: '#fff',
                                    borderRadius: '50%',
                                    background: 'rgba(18, 52, 86, 0.82)',
                                    border: '2px solid #fff',
                                    transform: 'translate(-50%, -50%)',
                                    boxShadow: '0 1px 6px rgba(0,0,0,.35)',
                                }}
                            >
                                <Focus size={16} strokeWidth={2} />
                            </span>
                        </button>
                    </Field>
                )}
            </Modal>

            <Modal
                open={!!usageTarget}
                onClose={() => setUsageTarget(null)}
                title="Где используется"
                width={560}
                footer={
                    <Button
                        variant="secondary"
                        onClick={() => setUsageTarget(null)}
                    >
                        Закрыть
                    </Button>
                }
            >
                <p style={{ marginTop: 0 }}>{usageTarget?.file_name}</p>
                {usageLoading ? (
                    <p aria-live="polite">Загрузка…</p>
                ) : usageError ? (
                    <div role="alert" className="ui-form-error-summary">
                        {usageError}
                    </div>
                ) : usageItems.length === 0 ? (
                    <p>
                        Файл нигде не используется и может быть перемещён в
                        корзину.
                    </p>
                ) : (
                    <ul
                        style={{
                            display: 'grid',
                            gap: 8,
                            margin: 0,
                            padding: 0,
                            listStyle: 'none',
                        }}
                    >
                        {usageItems.map((usage) => (
                            <li key={`${usage.type}:${usage.id}`}>
                                <a
                                    href={usage.edit_url}
                                    className="ui-link"
                                    style={{
                                        display: 'flex',
                                        justifyContent: 'space-between',
                                        gap: 12,
                                        padding: '10px 12px',
                                        border: '1px solid var(--color-divider)',
                                        borderRadius: 8,
                                    }}
                                >
                                    <span>{usage.title}</span>
                                    <span
                                        style={{
                                            color: 'var(--color-neutral-500)',
                                        }}
                                    >
                                        {usage.type}
                                    </span>
                                </a>
                            </li>
                        ))}
                    </ul>
                )}
            </Modal>
        </>
    );
}
