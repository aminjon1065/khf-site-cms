// Лёгкий клиент для внутренних JSON-эндпоинтов CMS (медиапикер редактора).
// Inertia использует свой транспорт; здесь нам нужен «сырой» fetch за JSON,
// поэтому CSRF-токен из cookie XSRF-TOKEN прокидываем вручную.

/** CSRF-токен из cookie XSRF-TOKEN, который Laravel ставит на каждый ответ. */
function xsrfToken(): string {
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);

    return match ? decodeURIComponent(match[1]) : '';
}

/**
 * Понятные сотруднику причины вместо «HTTP 413» и английских системных фраз
 * («This action is unauthorized.»).
 */
const STATUS_MESSAGES: Record<number, string> = {
    403: 'Недостаточно прав для этого действия.',
    404: 'Не найдено — возможно, материал уже удалили.',
    413: 'Файл слишком большой для загрузки.',
    419: 'Вы долго не работали — обновите страницу и повторите.',
    429: 'Слишком много действий подряд — подождите минуту.',
};

/**
 * Сообщение об ошибке для человека: у ошибок проверки (422) — текст первой
 * ошибки из ответа Laravel, у остальных — объяснение по коду ответа.
 */
async function errorMessage(res: Response): Promise<string> {
    if (res.status !== 422) {
        return (
            STATUS_MESSAGES[res.status] ??
            (res.status >= 500
                ? 'Сбой в системе — повторите попытку позже.'
                : `Не удалось выполнить действие (код ошибки ${res.status}).`)
        );
    }

    try {
        const body = (await res.json()) as { message?: string };

        return body.message ?? 'Проверьте заполненные поля.';
    } catch {
        return 'Проверьте заполненные поля.';
    }
}

/** JSON GET к внутреннему API CMS (в рамках сессии). */
export async function getJson<T>(url: string): Promise<T> {
    const res = await fetch(url, {
        headers: { Accept: 'application/json' },
        credentials: 'same-origin',
    });

    if (!res.ok) {
        throw new Error(await errorMessage(res));
    }

    return res.json() as Promise<T>;
}

/** Multipart POST (загрузка файла) с CSRF-заголовком; возвращает JSON. */
export async function postForm<T>(url: string, form: FormData): Promise<T> {
    const res = await fetch(url, {
        method: 'POST',
        headers: {
            Accept: 'application/json',
            'X-XSRF-TOKEN': xsrfToken(),
        },
        credentials: 'same-origin',
        body: form,
    });

    if (!res.ok) {
        throw new Error(await errorMessage(res));
    }

    return res.json() as Promise<T>;
}

/** JSON POST к внутреннему API CMS с CSRF-заголовком. */
export async function postJson<T>(url: string, data: unknown): Promise<T> {
    const res = await fetch(url, {
        method: 'POST',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-XSRF-TOKEN': xsrfToken(),
        },
        credentials: 'same-origin',
        body: JSON.stringify(data),
    });

    if (!res.ok) {
        throw new Error(await errorMessage(res));
    }

    return res.json() as Promise<T>;
}

/** JSON PATCH к внутреннему API CMS с CSRF-заголовком. */
export async function patchJson<T>(url: string, data: unknown): Promise<T> {
    const res = await fetch(url, {
        method: 'PATCH',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-XSRF-TOKEN': xsrfToken(),
        },
        credentials: 'same-origin',
        body: JSON.stringify(data),
    });

    if (!res.ok) {
        throw new Error(await errorMessage(res));
    }

    return res.json() as Promise<T>;
}
