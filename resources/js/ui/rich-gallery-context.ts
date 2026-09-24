import { createContext, useContext } from 'react';
import type { MediaItem } from './MediaPicker';

export interface RichGalleryItem {
    id: number | string;
    title: string;
    url: string;
    isExisting?: boolean;
    isPendingLibrary?: boolean;
    isPendingFile?: boolean;
    isRemoved?: boolean;
}

export interface RichGalleryContextValue {
    items: RichGalleryItem[];
    onAddFiles?: (files: File[]) => void;
    onAddLibrary?: (items: MediaItem[]) => void;
    onToggleRemove?: (id: number | string) => void;
    onOpenPicker?: () => void;
    /**
     * The edit goes to approval: the gallery's photos are shown but can't be
     * added or removed (PendingChangeService::assertNoMediaChanges).
     */
    locked?: boolean;
}

export const RichGalleryContext = createContext<RichGalleryContextValue | null>(
    null,
);

export function useRichGallery(): RichGalleryContextValue | null {
    return useContext(RichGalleryContext);
}
