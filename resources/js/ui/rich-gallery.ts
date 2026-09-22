import { Node, mergeAttributes } from '@tiptap/core';
import { ReactNodeViewRenderer } from '@tiptap/react';
import { RichGalleryView } from './RichGalleryView';

/**
 * Обработчик клика по чипу «Фотогалерея» (открыть медиатеку).
 */
let openPickerHandler: (() => void) | null = null;

export function registerGalleryPickerHandler(
    handler: (() => void) | null,
): void {
    openPickerHandler = handler;
}

export function triggerGalleryPicker(): void {
    openPickerHandler?.();
}

/**
 * Блок фотогалереи в теле материала: интерактивный React NodeView в редакторе
 * с живой сеткой фотографий, загрузкой снимков и выбором из медиатеки.
 *
 * Сериализуется как `<figure class="cms-gallery">…</figure>`: этот тег
 * разрешён профилем санитайзера 'news' (figure[class]) и распознаётся
 * публичной частью сайта для вывода интерактивной карусели (см. splitBodyByGallery).
 */
export const RichGallery = Node.create({
    name: 'gallery',
    group: 'block',
    atom: true,
    draggable: true,

    addAttributes() {
        return {
            images: {
                default: [],
                parseHTML: () => [],
                renderHTML: () => ({}),
            },
            columns: {
                default: 3,
                parseHTML: (el) =>
                    Number((el as HTMLElement).getAttribute('data-columns')) ||
                    3,
                renderHTML: (attrs) =>
                    attrs.columns ? { 'data-columns': attrs.columns } : {},
            },
            caption: {
                default: '',
                parseHTML: (el) =>
                    (el as HTMLElement)
                        .querySelector('figcaption')
                        ?.textContent?.trim() || '',
                renderHTML: () => ({}),
            },
        };
    },

    parseHTML() {
        return [{ tag: 'figure[class~="cms-gallery"]' }];
    },

    renderHTML({ HTMLAttributes }) {
        return [
            'figure',
            mergeAttributes(HTMLAttributes, { class: 'cms-gallery' }),
            ['span', { class: 'cms-gallery-chip' }, 'Фотогалерея'],
        ];
    },

    addNodeView() {
        return ReactNodeViewRenderer(RichGalleryView, {
            stopEvent: ({ event }) => {
                const target = event.target as HTMLElement | null;

                return Boolean(target?.closest('[data-re-gallery-ui]'));
            },
        });
    },

    addCommands() {
        return {
            insertGalleryMarker:
                () =>
                ({ chain }) =>
                    chain().focus().insertContent({ type: this.name }).run(),
        };
    },
});

declare module '@tiptap/core' {
    interface Commands<ReturnType> {
        gallery: {
            insertGalleryMarker: () => ReturnType;
        };
    }
}
