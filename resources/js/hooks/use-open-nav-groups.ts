import { useCallback, useSyncExternalStore } from 'react';

/**
 * Какие свёрнутые группы бокового меню человек раскрыл — запоминается в
 * этом браузере. Сервер и первый кадр рисуют группы свёрнутыми, поэтому
 * разметка SSR совпадает с гидратацией.
 */
const STORAGE_KEY = 'cms.sidebar.open-groups';
const CHANGE_EVENT = 'cms:sidebar-open-groups';

/** Если хранилище недоступно (приватный режим), выбор живёт до перезагрузки. */
let memory = '[]';

function read(): string {
    try {
        return window.localStorage.getItem(STORAGE_KEY) ?? memory;
    } catch {
        return memory;
    }
}

function write(value: string): void {
    memory = value;

    try {
        window.localStorage.setItem(STORAGE_KEY, value);
    } catch {
        // Остаётся в памяти вкладки.
    }

    window.dispatchEvent(new Event(CHANGE_EVENT));
}

function subscribe(onChange: () => void): () => void {
    window.addEventListener('storage', onChange);
    window.addEventListener(CHANGE_EVENT, onChange);

    return () => {
        window.removeEventListener('storage', onChange);
        window.removeEventListener(CHANGE_EVENT, onChange);
    };
}

function parse(raw: string): string[] {
    try {
        const value: unknown = JSON.parse(raw);

        return Array.isArray(value)
            ? value.filter((item): item is string => typeof item === 'string')
            : [];
    } catch {
        return [];
    }
}

export function useOpenNavGroups(): [string[], (group: string) => void] {
    const open = parse(useSyncExternalStore(subscribe, read, () => '[]'));

    const toggle = useCallback((group: string) => {
        const current = parse(read());

        write(
            JSON.stringify(
                current.includes(group)
                    ? current.filter((item) => item !== group)
                    : [...current, group],
            ),
        );
    }, []);

    return [open, toggle];
}
