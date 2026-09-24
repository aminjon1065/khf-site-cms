import { mergeAttributes } from '@tiptap/core';
import { Image as TiptapImage } from '@tiptap/extension-image';
import { ReactNodeViewRenderer } from '@tiptap/react';
import { RichImageView } from './RichImageView';

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

/**
 * HTMLPurifier заполнял отсутствующее описание именем файла: «IMG_2034.jpg»
 * ничего не описывает и в редакторе считается пустым описанием.
 */
const FILE_NAME = /\.(jpe?g|png|webp|gif|avif|heic|bmp|tiff?)$/i;

/** Ищет <img>: либо сам элемент, либо вложенный (для <figure>). */
function imgOf(el: HTMLElement): HTMLImageElement | null {
    return el instanceof HTMLImageElement ? el : el.querySelector('img');
}

/**
 * Изображение как фигура: в редакторе — React NodeView (подпись и инструменты
 * на самой картинке), в HTML — всегда `<figure>`, чтобы появление подписи
 * не меняло тег и не сбрасывало выделение.
 */
export const RichImage = TiptapImage.extend({
    addNodeView() {
        return ReactNodeViewRenderer(RichImageView, {
            stopEvent: ({ event }) => {
                const target = event.target as HTMLElement | null;

                return Boolean(target?.closest('[data-re-image-ui]'));
            },
        });
    },

    addAttributes() {
        return {
            src: {
                default: null,
                parseHTML: (el) =>
                    imgOf(el as HTMLElement)?.getAttribute('src') ?? null,
            },
            alt: {
                default: null,
                parseHTML: (el) => {
                    const alt =
                        imgOf(el as HTMLElement)?.getAttribute('alt') ?? null;

                    return alt !== null && FILE_NAME.test(alt.trim())
                        ? null
                        : alt;
                },
            },
            /** Декоративное фото: описание не нужно (`data-decorative`). */
            decorative: {
                default: false,
                renderHTML: () => ({}),
                parseHTML: (el) =>
                    imgOf(el as HTMLElement)?.hasAttribute('data-decorative') ??
                    false,
            },
            title: {
                default: null,
                parseHTML: (el) =>
                    imgOf(el as HTMLElement)?.getAttribute('title') ?? null,
            },
            align: {
                default: null,
                renderHTML: () => ({}),
                parseHTML: (el) =>
                    readClass((el as HTMLElement).className, 'align'),
            },
            size: {
                default: null,
                renderHTML: () => ({}),
                parseHTML: (el) =>
                    readClass((el as HTMLElement).className, 'size'),
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
            mediaId: {
                default: null,
                renderHTML: () => ({}),
                parseHTML: (el) =>
                    imgOf(el as HTMLElement)?.getAttribute('data-media-id') ??
                    null,
            },
        };
    },

    parseHTML() {
        return [
            {
                tag: 'figure',
                getAttrs: (el) => (imgOf(el as HTMLElement) ? {} : false),
            },
            { tag: 'img[src]' },
        ];
    },

    renderHTML({ node }) {
        const {
            src,
            alt,
            title,
            align,
            size,
            caption,
            srcset,
            mediaId,
            decorative,
        } = node.attrs;
        const wrap = [
            're-figure',
            align && ALIGN_CLASS[align],
            size && SIZE_CLASS[size],
        ]
            .filter(Boolean)
            .join(' ');

        // alt пишется всегда: без него HTMLPurifier подставит имя файла.
        const imgAttrs: Record<string, string> = {
            src: src ?? '',
            alt: decorative ? '' : (alt ?? ''),
        };

        if (decorative) {
            imgAttrs['data-decorative'] = 'true';
        }

        if (title) {
            imgAttrs.title = title;
        }

        if (srcset) {
            imgAttrs.srcset = srcset;
            imgAttrs.sizes = SIZES_ATTR[size ?? 'full'] ?? SIZES_ATTR.full;
        }

        if (mediaId) {
            imgAttrs['data-media-id'] = String(mediaId);
        }

        const img = ['img', mergeAttributes(imgAttrs, { class: 're-img' })];
        const attrs = { class: wrap || 're-figure' };

        if (caption) {
            return ['figure', attrs, img, ['figcaption', {}, String(caption)]];
        }

        return ['figure', attrs, img];
    },
});
