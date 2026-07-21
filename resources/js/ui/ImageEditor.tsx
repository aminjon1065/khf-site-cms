import Cropper from 'cropperjs';
import 'cropperjs/dist/cropper.css';
import {
    FlipHorizontal2,
    FlipVertical2,
    RefreshCw,
    RotateCcw,
    RotateCw,
    ZoomIn,
    ZoomOut,
} from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import type { ReactNode } from 'react';
import { postForm } from '@/lib/http';
import { Button } from './Button';
import type { MediaItem } from './MediaPicker';
import { Modal } from './Overlay';

interface Props {
    open: boolean;
    source: MediaItem | null;
    onClose: () => void;
    onSaved: (item: MediaItem) => void;
}

/** Пропорции кадрирования (`null` — свободно). */
const ASPECTS: { label: string; value: number | null }[] = [
    { label: 'Свободно', value: null },
    { label: '1:1', value: 1 },
    { label: '4:3', value: 4 / 3 },
    { label: '3:2', value: 3 / 2 },
    { label: '16:9', value: 16 / 9 },
    { label: '3:4', value: 3 / 4 },
];

function IeBtn({
    icon,
    label,
    onClick,
}: {
    icon: ReactNode;
    label: string;
    onClick: () => void;
}) {
    return (
        <button
            type="button"
            className="ie-btn"
            title={label}
            aria-label={label}
            onClick={onClick}
        >
            {icon}
        </button>
    );
}

/**
 * WordPress-подобный редактор изображения: свободное/пропорциональное
 * кадрирование, поворот, отражение и масштаб (Cropper.js). Обработка идёт на
 * клиенте через canvas; результат загружается новым ассетом в медиатеку
 * (оригинал не меняется — неразрушающее редактирование).
 */
export function ImageEditor({ open, source, onClose, onSaved }: Props) {
    const imgRef = useRef<HTMLImageElement>(null);
    const cropperRef = useRef<Cropper | null>(null);
    const flip = useRef({ x: 1, y: 1 });
    const [aspect, setAspect] = useState<number | null>(null);
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState<string | null>(null);

    useEffect(() => {
        const el = imgRef.current;

        if (!open || !source || !el) {
            return;
        }

        flip.current = { x: 1, y: 1 };
        setAspect(null);
        const cropper = new Cropper(el, {
            viewMode: 1,
            autoCropArea: 1,
            background: false,
            responsive: true,
            checkOrientation: false,
        });
        cropperRef.current = cropper;

        return () => {
            cropper.destroy();
            cropperRef.current = null;
        };
    }, [open, source]);

    const applyAspect = (value: number | null) => {
        setAspect(value);
        cropperRef.current?.setAspectRatio(value ?? NaN);
    };

    const flipX = () => {
        flip.current.x *= -1;
        cropperRef.current?.scaleX(flip.current.x);
    };
    const flipY = () => {
        flip.current.y *= -1;
        cropperRef.current?.scaleY(flip.current.y);
    };
    const reset = () => {
        flip.current = { x: 1, y: 1 };
        cropperRef.current?.reset();
    };

    const save = async () => {
        const cropper = cropperRef.current;

        if (!cropper || !source) {
            return;
        }

        setSaving(true);
        setError(null);

        try {
            const canvas = cropper.getCroppedCanvas({
                maxWidth: 2400,
                maxHeight: 2400,
                imageSmoothingQuality: 'high',
            });
            const blob = await new Promise<Blob | null>((resolve) =>
                canvas.toBlob((b) => resolve(b), 'image/jpeg', 0.9),
            );

            if (!blob) {
                throw new Error('Не удалось сформировать изображение.');
            }

            const base = (source.name ?? source.file_name).replace(
                /\.[^.]+$/,
                '',
            );
            const file = new File([blob], `${base}-edited.jpg`, {
                type: 'image/jpeg',
            });
            const form = new FormData();
            form.append('file', file);
            form.append('title', `${source.name ?? source.file_name} (ред.)`);
            const res = await postForm<{ data: MediaItem }>(
                '/media/library',
                form,
            );
            onSaved(res.data);
        } catch (e) {
            setError((e as Error).message);
        } finally {
            setSaving(false);
        }
    };

    if (!open || !source) {
        return null;
    }

    return (
        <Modal
            open={open}
            onClose={onClose}
            title="Редактор изображения"
            width={860}
            footer={
                <>
                    {error && (
                        <span
                            style={{
                                color: 'var(--danger)',
                                fontSize: 13,
                                marginRight: 'auto',
                            }}
                        >
                            {error}
                        </span>
                    )}
                    <Button variant="ghost" onClick={onClose}>
                        Отмена
                    </Button>
                    <Button
                        variant="primary"
                        loading={saving}
                        onClick={() => void save()}
                    >
                        Сохранить как новое
                    </Button>
                </>
            }
        >
            <div className="ie-toolbar">
                <IeBtn
                    icon={<RotateCcw size={16} />}
                    label="Повернуть влево"
                    onClick={() => cropperRef.current?.rotate(-90)}
                />
                <IeBtn
                    icon={<RotateCw size={16} />}
                    label="Повернуть вправо"
                    onClick={() => cropperRef.current?.rotate(90)}
                />
                <IeBtn
                    icon={<FlipHorizontal2 size={16} />}
                    label="Отразить по горизонтали"
                    onClick={flipX}
                />
                <IeBtn
                    icon={<FlipVertical2 size={16} />}
                    label="Отразить по вертикали"
                    onClick={flipY}
                />
                <span className="ie-sep" aria-hidden />
                <IeBtn
                    icon={<ZoomIn size={16} />}
                    label="Приблизить"
                    onClick={() => cropperRef.current?.zoom(0.1)}
                />
                <IeBtn
                    icon={<ZoomOut size={16} />}
                    label="Отдалить"
                    onClick={() => cropperRef.current?.zoom(-0.1)}
                />
                <IeBtn
                    icon={<RefreshCw size={16} />}
                    label="Сбросить"
                    onClick={reset}
                />
                <span className="ie-sep" aria-hidden />
                <div className="ie-aspects">
                    {ASPECTS.map((a) => (
                        <button
                            key={a.label}
                            type="button"
                            className={`ie-aspect${aspect === a.value ? 'is-active' : ''}`}
                            onClick={() => applyAspect(a.value)}
                        >
                            {a.label}
                        </button>
                    ))}
                </div>
            </div>

            <div className="ie-stage">
                <img
                    ref={imgRef}
                    src={source.url}
                    className="ie-image"
                    alt=""
                />
            </div>
        </Modal>
    );
}
