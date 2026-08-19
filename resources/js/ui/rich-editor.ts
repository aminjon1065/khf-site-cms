/** Чистые хелперы редактора: их можно гонять без Tiptap. */

export function normalizeLinkUrl(raw: string): string | null {
    const value = raw.trim();

    if (value === '') {
        return '';
    }

    if (
        value.startsWith('#') ||
        value.startsWith('/') ||
        value.startsWith('mailto:') ||
        value.startsWith('tel:')
    ) {
        return value;
    }

    const withProtocol = /^https?:\/\//i.test(value)
        ? value
        : `https://${value}`;

    try {
        const url = new URL(withProtocol);

        if (url.protocol !== 'http:' && url.protocol !== 'https:') {
            return null;
        }

        return url.toString();
    } catch {
        return null;
    }
}

export function parseYoutubeUrl(raw: string): string | null {
    const value = raw.trim();

    if (value === '') {
        return null;
    }

    const withProtocol = /^https?:\/\//i.test(value)
        ? value
        : `https://${value}`;

    try {
        const url = new URL(withProtocol);
        const host = url.hostname.replace(/^www\./, '').replace(/^m\./, '');

        if (host === 'youtu.be') {
            const id = url.pathname.replace(/^\//, '').split('/')[0] ?? '';

            return /^[\w-]{11}$/.test(id)
                ? `https://www.youtube.com/watch?v=${id}`
                : null;
        }

        if (host === 'youtube.com' || host === 'youtube-nocookie.com') {
            if (url.pathname.startsWith('/embed/')) {
                const id = url.pathname.split('/')[2] ?? '';

                return /^[\w-]{11}$/.test(id)
                    ? `https://www.youtube.com/watch?v=${id}`
                    : null;
            }

            const id = url.searchParams.get('v') ?? '';

            return /^[\w-]{11}$/.test(id)
                ? `https://www.youtube.com/watch?v=${id}`
                : null;
        }

        return null;
    } catch {
        return null;
    }
}

export function countWords(text: string): number {
    const trimmed = text.replace(/\s+/g, ' ').trim();

    return trimmed === '' ? 0 : trimmed.split(' ').length;
}

export function readingMinutes(words: number): number {
    if (words === 0) {
        return 0;
    }

    return Math.max(1, Math.round(words / 180));
}

const TABLE_BLOCK = /<table\b[\s\S]*?<\/table>/gi;

export function htmlHasTable(html: string): boolean {
    return /<table\b/i.test(html);
}

export function htmlHasYoutube(html: string): boolean {
    return /data-youtube-video|youtube\.com\/embed|youtube-nocookie\.com\/embed/i.test(
        html,
    );
}

/** Удаляет последнюю таблицу из HTML — запасной путь, если команда Tiptap не сработала. */
export function stripLastTable(html: string): string {
    const matches = [...html.matchAll(TABLE_BLOCK)];
    const last = matches.at(-1);

    if (!last || last.index === undefined) {
        return html;
    }

    return `${html.slice(0, last.index)}${html.slice(last.index + last[0].length)}`;
}

const PASTE_STYLE_KEEP =
    /^(color|background-color|text-align|width|min-width)$/i;

function keepSafeInlineStyle(style: string): string {
    return style
        .split(';')
        .map((declaration) => declaration.trim())
        .filter((declaration) => {
            const property = declaration.split(':')[0]?.trim() ?? '';

            return PASTE_STYLE_KEEP.test(property);
        })
        .join('; ');
}

/**
 * Вычищает мусор Word/Google Docs при вставке, оставляя цвет, заливку
 * ячеек и ширину столбцов — то, что редактор сам умеет сохранять.
 */
export function cleanPastedHtml(html: string): string {
    return html
        .replace(/<!--[\s\S]*?-->/g, '')
        .replace(/<\/?(?:meta|link|o:p|w:[^>\s]*)[^>]*>/gi, '')
        .replace(/\s(?:class|lang|face|id|align)="[^"]*"/gi, '')
        .replace(/\sstyle="([^"]*)"/gi, (_, style: string) => {
            const kept = keepSafeInlineStyle(style);

            return kept === '' ? '' : ` style="${kept}"`;
        })
        .replace(/<\/?span\s*>/gi, '');
}

/** Палитра заливки ячеек — спокойные казённые тона, как в бланках КЧС. */
export const TABLE_CELL_FILLS: { label: string; value: string | null }[] = [
    { label: 'Без заливки', value: null },
    { label: 'Белый', value: '#ffffff' },
    { label: 'Серый', value: '#f4f5f8' },
    { label: 'Синий КЧС', value: '#d7e2ea' },
    { label: 'Голубой', value: '#e8f1f8' },
    { label: 'Жёлтый', value: '#f7efd4' },
    { label: 'Зелёный', value: '#dceee2' },
    { label: 'Красный', value: '#f3d6d2' },
];

/** Приводит CSS-цвет (hex / rgb) к #rrggbb, иначе null. */
export function parseCssColor(raw: string | null | undefined): string | null {
    if (raw === null || raw === undefined) {
        return null;
    }

    const value = raw.trim().toLowerCase();

    if (value === '' || value === 'transparent') {
        return null;
    }

    const hex = value.match(/^#([0-9a-f]{3}|[0-9a-f]{6})$/);

    if (hex) {
        const digits = hex[1] ?? '';

        if (digits.length === 3) {
            return `#${digits
                .split('')
                .map((digit) => `${digit}${digit}`)
                .join('')}`;
        }

        return `#${digits}`;
    }

    const rgb = value.match(/^rgba?\(\s*(\d+)\s*[, ]\s*(\d+)\s*[, ]\s*(\d+)/);

    if (!rgb) {
        return null;
    }

    const toHex = (channel: string): string =>
        Number(channel).toString(16).padStart(2, '0');

    return `#${toHex(rgb[1] ?? '0')}${toHex(rgb[2] ?? '0')}${toHex(rgb[3] ?? '0')}`;
}
