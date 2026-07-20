import { mergeAttributes } from '@tiptap/core';
import { Image as TiptapImage } from '@tiptap/extension-image';

export type ImageAlign = 'left' | 'center' | 'right' | null;
export type ImageSize = 'small' | 'medium' | 'large' | 'full' | null;

const ALIGN_CLASS: Record<string, string> = {
    left: 'align-left',
    center: 'align-center',
    right: 'align-right',
};
const SIZE_CLASS: Record<string, string> = {
    small: 'size-small',
    medium: 'size-medium',
    large: 'size-large',
    full: 'size-full',
};
/** Атрибут `sizes` под класс размера (для корректного выбора из srcset). */
const SIZES_ATTR: Record<string, string> = {
    small: '(max-width: 920px) 40vw, 180px',
    medium: '(max-width: 920px) 55vw, 360px',
    large: '(max-width: 920px) 80vw, 540px',
    full: '(max-width: 920px) 100vw, 720px',
};

/** Достаёт суффикс класса `prefix-<value>` (напр. `align-center` → `center`). */
function readClass(className: string, prefix: string): string | null {
    const match = className.match(new RegExp(`(?:^|\\s)${prefix}-([a-z]+)`));

    return match ? match[1] : null;
}

/** Ищет <img>: либо сам элемент, либо вложенный (для <figure>). */
function imgOf(el: HTMLElement): HTMLImageElement | null {
    return el instanceof HTMLImageElement ? el : el.querySelector('img');
}

/**
 * Изображение с параметрами вставки (как в WordPress): выравнивание, размер и
 * подпись. С подписью рендерится в `<figure><img><figcaption>`, без — обычным
 * `<img>`. Выравнивание/размер хранятся классами `align-*` / `size-*`.
 */
export const RichImage = TiptapImage.extend({
    addAttributes() {
        return {
            src: {
                default: null,
                parseHTML: (el) => imgOf(el as HTMLElement)?.getAttribute('src') ?? null,
            },
            alt: {
                default: null,
                parseHTML: (el) => imgOf(el as HTMLElement)?.getAttribute('alt') ?? null,
            },
            title: {
                default: null,
                parseHTML: (el) => imgOf(el as HTMLElement)?.getAttribute('title') ?? null,
            },
            align: {
                default: null,
                renderHTML: () => ({}),
                parseHTML: (el) => readClass((el as HTMLElement).className, 'align'),
            },
            size: {
                default: null,
                renderHTML: () => ({}),
                parseHTML: (el) => readClass((el as HTMLElement).className, 'size'),
            },
            caption: {
                default: null,
                renderHTML: () => ({}),
                parseHTML: (el) =>
                    (el as HTMLElement)
                        .querySelector('figcaption')
                        ?.textContent?.trim() || null,
            },
            srcset: {
                default: null,
                renderHTML: () => ({}),
                parseHTML: (el) =>
                    imgOf(el as HTMLElement)?.getAttribute('srcset') ?? null,
            },
        };
    },

    parseHTML() {
        return [
            { tag: 'figure', getAttrs: (el) => (imgOf(el as HTMLElement) ? {} : false) },
            { tag: 'img[src]' },
        ];
    },

    renderHTML({ node }) {
        const { src, alt, title, align, size, caption, srcset } = node.attrs;
        const wrap = ['re-figure', align && ALIGN_CLASS[align], size && SIZE_CLASS[size]]
            .filter(Boolean)
            .join(' ');
        const bare = ['re-img', align && ALIGN_CLASS[align], size && SIZE_CLASS[size]]
            .filter(Boolean)
            .join(' ');

        const imgAttrs: Record<string, string> = { src: src ?? '' };

        if (alt) {
            imgAttrs.alt = alt;
        }

        if (title) {
            imgAttrs.title = title;
        }

        if (srcset) {
            imgAttrs.srcset = srcset;
            imgAttrs.sizes = SIZES_ATTR[size ?? 'full'] ?? SIZES_ATTR.full;
        }

        if (caption) {
            return [
                'figure',
                { class: wrap },
                ['img', mergeAttributes(imgAttrs, { class: 're-img' })],
                ['figcaption', {}, String(caption)],
            ];
        }

        return ['img', mergeAttributes(imgAttrs, { class: bare })];
    },
});
