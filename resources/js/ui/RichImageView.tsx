import type { ReactNodeViewProps } from '@tiptap/react';
import { NodeViewWrapper } from '@tiptap/react';
import {
    AlignCenter,
    AlignLeft,
    AlignRight,
    Crop,
    Images,
    Trash2,
    Type,
} from 'lucide-react';
import { useRef, useState } from 'react';
import { cn } from '@/lib/utils';
import { ImageEditor } from './ImageEditor';
import { MediaPicker } from './MediaPicker';
import type { MediaItem } from './MediaPicker';
import type { ImageAlign, ImageSize } from './rich-image';

const ALIGN: { value: ImageAlign; label: string; icon: typeof AlignLeft }[] = [
    { value: 'left', label: 'Слева', icon: AlignLeft },
    { value: 'center', label: 'По центру', icon: AlignCenter },
    { value: 'right', label: 'Справа', icon: AlignRight },
];

const SIZE: { value: ImageSize; label: string; short: string }[] = [
    { value: 'small', label: 'Маленький', short: 'S' },
    { value: 'medium', label: 'Средний', short: 'M' },
    { value: 'large', label: 'Большой', short: 'L' },
    { value: 'full', label: 'На всю ширину', short: '↔' },
];

/**
 * Фото в тексте как фигура: клик выделяет, подпись правится на месте,
 * кадрирование / замена / размер — на самой картинке, а не в чужом тулбаре.
 */
export function RichImageView({
    node,
    selected,
    updateAttributes,
    deleteNode,
    editor,
    getPos,
}: ReactNodeViewProps) {
    const { src, alt, caption, align, size, srcset, mediaId } = node.attrs as {
        src: string;
        alt: string | null;
        caption: string | null;
        align: ImageAlign;
        size: ImageSize;
        srcset: string | null;
        mediaId: string | number | null;
    };
    const [replacing, setReplacing] = useState(false);
    const [cropping, setCropping] = useState(false);
    const altRef = useRef<HTMLInputElement>(null);

    const select = () => {
        const pos = getPos();

        if (typeof pos === 'number') {
            editor.chain().setNodeSelection(pos).focus().run();
        }
    };

    const applyMedia = (item: MediaItem) => {
        updateAttributes({
            src: item.url,
            srcset: item.srcset,
            mediaId: item.id,
            alt: item.alt ?? item.name ?? alt,
            caption: caption || item.caption,
        });
        setReplacing(false);
        setCropping(false);
        select();
    };

    const cropSource: MediaItem = {
        id: Number(mediaId) || 0,
        url: src,
        name: alt,
        file_name: 'image.jpg',
        ext: 'jpg',
        size: '',
        srcset,
        alt,
        caption,
    };

    return (
        <NodeViewWrapper
            as="div"
            className={cn(
                're-image-view',
                align && `align-${align}`,
                size && `size-${size}`,
                selected && 'is-selected',
            )}
            data-drag-handle=""
        >
            <figure className="re-figure">
                <div className="re-figure-frame">
                    <img
                        src={src}
                        alt={alt ?? ''}
                        srcSet={srcset ?? undefined}
                        draggable={false}
                        onClick={select}
                    />
                    {selected && (
                        <div
                            className="re-figure-tools"
                            data-re-image-ui=""
                            contentEditable={false}
                        >
                            {ALIGN.map((option) => (
                                <button
                                    key={option.label}
                                    type="button"
                                    className={cn(
                                        're-btn',
                                        align === option.value && 'is-active',
                                    )}
                                    title={option.label}
                                    aria-label={option.label}
                                    aria-pressed={align === option.value}
                                    onMouseDown={(e) => e.preventDefault()}
                                    onClick={() =>
                                        updateAttributes({
                                            align: option.value,
                                        })
                                    }
                                >
                                    <option.icon size={15} />
                                </button>
                            ))}
                            <span className="re-sep" aria-hidden />
                            {SIZE.map((option) => (
                                <button
                                    key={option.short}
                                    type="button"
                                    className={cn(
                                        're-btn re-size-btn',
                                        size === option.value && 'is-active',
                                    )}
                                    title={option.label}
                                    aria-label={option.label}
                                    aria-pressed={size === option.value}
                                    onMouseDown={(e) => e.preventDefault()}
                                    onClick={() =>
                                        updateAttributes({
                                            size: option.value,
                                        })
                                    }
                                >
                                    {option.short}
                                </button>
                            ))}
                            <span className="re-sep" aria-hidden />
                            <button
                                type="button"
                                className="re-btn"
                                title="Заменить"
                                aria-label="Заменить изображение"
                                onMouseDown={(e) => e.preventDefault()}
                                onClick={() => setReplacing(true)}
                            >
                                <Images size={15} />
                            </button>
                            <button
                                type="button"
                                className="re-btn"
                                title="Кадрировать и повернуть"
                                aria-label="Кадрировать и повернуть"
                                onMouseDown={(e) => e.preventDefault()}
                                onClick={() => setCropping(true)}
                            >
                                <Crop size={15} />
                            </button>
                            <button
                                type="button"
                                className="re-btn"
                                title="Alt-текст"
                                aria-label="Alt-текст"
                                onMouseDown={(e) => e.preventDefault()}
                                onClick={() => altRef.current?.focus()}
                            >
                                <Type size={15} />
                            </button>
                            <button
                                type="button"
                                className="re-btn"
                                title="Удалить"
                                aria-label="Удалить изображение"
                                onMouseDown={(e) => e.preventDefault()}
                                onClick={() => deleteNode()}
                            >
                                <Trash2 size={15} />
                            </button>
                        </div>
                    )}
                </div>
                {selected && (
                    <label className="re-figure-alt">
                        <span>Alt</span>
                        <input
                            ref={altRef}
                            value={alt ?? ''}
                            onChange={(e) =>
                                updateAttributes({
                                    alt: e.target.value,
                                })
                            }
                            placeholder="Описание для скринридеров"
                            onMouseDown={(e) => e.stopPropagation()}
                            onKeyDown={(e) => e.stopPropagation()}
                        />
                    </label>
                )}
                {(selected || Boolean(caption)) && (
                    <input
                        className="re-figure-caption"
                        data-re-image-ui=""
                        value={caption ?? ''}
                        placeholder="Добавить подпись…"
                        aria-label="Подпись к изображению"
                        onChange={(e) =>
                            updateAttributes({ caption: e.target.value })
                        }
                        onBlur={(e) =>
                            updateAttributes({
                                caption: e.target.value.trim() || null,
                            })
                        }
                        onMouseDown={(e) => e.stopPropagation()}
                        onKeyDown={(e) => {
                            e.stopPropagation();

                            if (e.key === 'Enter') {
                                e.preventDefault();
                                (e.target as HTMLInputElement).blur();
                            }
                        }}
                    />
                )}
            </figure>
            <MediaPicker
                open={replacing}
                onClose={() => setReplacing(false)}
                onSelect={applyMedia}
            />
            <ImageEditor
                open={cropping}
                source={cropping ? cropSource : null}
                onClose={() => setCropping(false)}
                onSaved={applyMedia}
            />
        </NodeViewWrapper>
    );
}
