import { Images, Upload } from 'lucide-react';
import type { RefObject } from 'react';
import { Blueprint } from '@/ui/Blueprint';
import { Button } from '@/ui/Button';
import { Checkbox } from '@/ui/Field';
import { MediaPicker } from '@/ui/MediaPicker';
import type { MediaItem } from '@/ui/MediaPicker';

export function CoverCard({
    coverSrc,
    coverFileRef,
    onFileChange,
    onUploadClick,
    coverPicker,
    setCoverPicker,
    pickCoverFromLibrary,
    hasExistingCover,
    coverRemove,
    setCoverRemove,
}: {
    coverSrc: string | null;
    coverFileRef: RefObject<HTMLInputElement | null>;
    onFileChange: (file: File | null) => void;
    onUploadClick: () => void;
    coverPicker: boolean;
    setCoverPicker: (open: boolean) => void;
    pickCoverFromLibrary: (item: MediaItem) => void;
    hasExistingCover: boolean;
    coverRemove: boolean;
    setCoverRemove: (remove: boolean) => void;
}) {
    return (
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
                    alt=""
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
                    onFileChange(file);
                    e.target.value = '';
                }}
            />
            <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap' }}>
                <Button
                    variant="secondary"
                    icon={<Upload size={15} strokeWidth={1.75} />}
                    onClick={onUploadClick}
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

            {hasExistingCover && (
                <Checkbox
                    className="mt-2"
                    label="Удалить текущую обложку"
                    checked={coverRemove}
                    onChange={(e) => setCoverRemove(e.target.checked)}
                />
            )}

            <MediaPicker
                open={coverPicker}
                onClose={() => setCoverPicker(false)}
                onSelect={pickCoverFromLibrary}
            />
        </Blueprint>
    );
}
