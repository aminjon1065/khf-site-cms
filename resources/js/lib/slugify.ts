/**
 * Longest address the site can hold: it turns a slug into a file name and
 * cache tags (App\Support\Slug::MAX_LENGTH on the server).
 */
export const SLUG_MAX_LENGTH = 180;

/**
 * Cuts a slug to the limit between words; a single overlong word is cut
 * where the limit falls. Same rule as App\Support\Slug::limit().
 */
export function limitSlug(slug: string, max: number = SLUG_MAX_LENGTH): string {
    if (slug.length <= max) {
        return slug;
    }

    let cut = slug.slice(0, max);

    if (slug[max] !== '-') {
        const boundary = cut.lastIndexOf('-');

        if (boundary >= Math.floor(max / 2)) {
            cut = cut.slice(0, boundary);
        }
    }

    return cut.replace(/-+$/, '');
}

/**
 * Transliterates Russian and Tajik Cyrillic text into clean, SEO-friendly Latin slugs.
 */
export function slugify(text: string): string {
    // prettier-ignore
    const map: Record<string, string> = {
        а: 'a', б: 'b', в: 'v', г: 'g', д: 'd', е: 'e', ё: 'yo', ж: 'zh', з: 'z', и: 'i',
        й: 'y', к: 'k', л: 'l', м: 'm', н: 'n', о: 'o', п: 'p', р: 'r', с: 's', т: 't',
        у: 'u', ф: 'f', х: 'kh', ц: 'ts', ч: 'ch', ш: 'sh', щ: 'shch', ъ: '', ы: 'y', ь: '',
        э: 'e', ю: 'yu', я: 'ya',
        // Tajik specific letters:
        ғ: 'gh', ӣ: 'i', ӯ: 'u', ҳ: 'h', ҷ: 'j', қ: 'q',
    };

    const lower = text.toLowerCase();
    let res = '';

    for (const char of lower) {
        if (map[char] !== undefined) {
            res += map[char];
        } else if (/[a-z0-9]/.test(char)) {
            res += char;
        } else if (/[\s\-_.,:;!?'"()[\]]+/.test(char)) {
            res += '-';
        }
    }

    return limitSlug(res.replace(/-+/g, '-').replace(/^-|-$/g, ''));
}
