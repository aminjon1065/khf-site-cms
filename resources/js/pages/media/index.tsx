import { Head, router } from '@inertiajs/react';
import { Check, Copy, FileText, Pencil, Trash2, Upload } from 'lucide-react';
import { useRef, useState } from 'react';
import { useClipboard } from '@/hooks/use-clipboard';
import { useCan } from '@/lib/auth';
import { Tag } from '@/ui/Badge';
import { Blueprint } from '@/ui/Blueprint';
import { Button, IconButton } from '@/ui/Button';
import { Pagination } from '@/ui/DataTable';
import { Field, Input, Select, Textarea } from '@/ui/Field';
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
    uploaded_at: string | null;
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
    filters: { kind: string; search: string };
    stats: { total: number; images: number; library: number };
}

const KIND_OPTIONS = [
    { value: '', label: 'Все файлы' },
    { value: 'image', label: 'Изображения' },
    { value: 'file', label: 'Документы' },
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
    });
    const [editSaving, setEditSaving] = useState(false);

    const openEdit = (item: MediaItem) => {
        setEditForm({
            name: item.title ?? item.name ?? '',
            alt: item.alt ?? '',
            caption: item.caption ?? '',
        });
        setEditTarget(item);
    };

    const saveEdit = () => {
        if (!editTarget) {
            return;
        }

        setEditSaving(true);
        router.put(`/media/${editTarget.id}`, editForm, {
            preserveScroll: true,
            onSuccess: () => setEditTarget(null),
            onFinish: () => setEditSaving(false),
        });
    };

    const reload = (patch: Partial<Props['filters']>) => {
        router.get(
            '/media',
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

        router.post('/media', fd, {
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
                subtitle={`Всего файлов: ${stats.total} · изображений: ${stats.images} · в библиотеке: ${stats.library}`}
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
                    value={filters.kind}
                    options={KIND_OPTIONS}
                    onChange={(e) => reload({ kind: e.target.value })}
                    style={{ width: 'auto' }}
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
                                        alt={item.name}
                                        style={{
                                            width: '100%',
                                            height: '100%',
                                            objectFit: 'cover',
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
                                        {item.usage}
                                    </Tag>
                                    <span
                                        style={{
                                            fontSize: 11.5,
                                            color: 'var(--color-neutral-500)',
                                        }}
                                    >
                                        {item.size}
                                    </span>
                                    <span style={{ flex: 1 }} />
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
                                        item.kind === 'image' &&
                                        can('media.create') && (
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
                                    {item.owned && can('media.delete') && (
                                        <IconButton
                                            label="Удалить"
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
                        ? `Файл «${deleteTarget.file_name}» будет удалён без возможности восстановления.`
                        : ''
                }
                confirmLabel="Удалить"
                onConfirm={() => {
                    if (!deleteTarget) {
                        return;
                    }

                    setProcessing(true);
                    router.delete(`/media/${deleteTarget.id}`, {
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
                        onChange={(e) =>
                            setEditForm({ ...editForm, alt: e.target.value })
                        }
                        maxLength={255}
                        placeholder="Что изображено на картинке"
                    />
                </Field>
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
            </Modal>
        </>
    );
}
