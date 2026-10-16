/**
 * A version brought back into an editor — from the history, a conflict or a
 * local copy — in the shape of the editor's own form.
 *
 * The server keeps versions as model snapshots, not as form data: an empty
 * translation map is `[]`, a missing language has no key, an empty field is
 * `null`. Put into the form as it is, that crashed the editor on the first
 * `summary.ru.trim()`. Every field of the version that the form has is
 * conformed to the form's value: strings stay strings, maps keep all their
 * keys, and what the version doesn't have is empty — restoring a version
 * where a language was blank makes it blank again.
 */
export function conformToForm<T extends object>(
    form: T,
    version: unknown,
): Partial<T> {
    if (!isPlainObject(version)) {
        return {};
    }

    const result: Record<string, unknown> = {};
    const current = form as Record<string, unknown>;

    for (const key of Object.keys(version)) {
        if (key in current) {
            result[key] = conform(current[key], version[key]);
        }
    }

    return result as Partial<T>;
}

function conform(current: unknown, incoming: unknown): unknown {
    if (typeof current === 'string') {
        return typeof incoming === 'string' || typeof incoming === 'number'
            ? String(incoming)
            : '';
    }

    if (typeof current === 'boolean') {
        return Boolean(incoming);
    }

    if (Array.isArray(current)) {
        return Array.isArray(incoming) ? incoming : [];
    }

    if (isPlainObject(current)) {
        const source = isPlainObject(incoming) ? incoming : {};

        return Object.fromEntries(
            Object.keys(current).map((key) => [
                key,
                conform(current[key], source[key]),
            ]),
        );
    }

    // Numbers, null and anything the form leaves untyped (an empty select
    // is `null`): the version's value as it is.
    return incoming === undefined ? current : incoming;
}

function isPlainObject(value: unknown): value is Record<string, unknown> {
    return typeof value === 'object' && value !== null && !Array.isArray(value);
}
