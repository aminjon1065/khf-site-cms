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

/**
 * Вычищает мусор Word/Google Docs при вставке, не трогая смысловые теги.
 */
export function cleanPastedHtml(html: string): string {
    return html
        .replace(/<!--[\s\S]*?-->/g, '')
        .replace(/<\/?(?:meta|link|o:p|w:[^>\s]*)[^>]*>/gi, '')
        .replace(/\s(?:class|style|lang|face|id|align)="[^"]*"/gi, '')
        .replace(/<\/?span\s*>/gi, '');
}
