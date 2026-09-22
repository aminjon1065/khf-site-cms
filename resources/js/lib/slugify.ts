/**
 * Transliterates Russian and Tajik Cyrillic text into clean, SEO-friendly Latin slugs.
 */
export function slugify(text: string): string {
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

    return res.replace(/-+/g, '-').replace(/^-|-$/g, '');
}
