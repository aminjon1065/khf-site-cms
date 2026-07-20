// Лёгкий клиент для внутренних JSON-эндпоинтов CMS (медиапикер редактора).
// Inertia использует свой транспорт; здесь нам нужен «сырой» fetch за JSON,
// поэтому CSRF-токен из cookie XSRF-TOKEN прокидываем вручную.

/** CSRF-токен из cookie XSRF-TOKEN, который Laravel ставит на каждый ответ. */
function xsrfToken(): string {
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);

    return match ? decodeURIComponent(match[1]) : '';
}

/** Читает сообщение об ошибке из JSON-ответа Laravel, если оно есть. */
async function errorMessage(res: Response): Promise<string> {
    try {
        const body = (await res.json()) as { message?: string };

        return body.message ?? `HTTP ${res.status}`;
    } catch {
        return `HTTP ${res.status}`;
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
