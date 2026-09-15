import { useEffect, useRef } from 'react';
import { useAuth } from '@/lib/auth';

/**
 * Запоминает выбранный срез списка (tabs: все / черновики / …) для
 * конкретного пользователя в localStorage. При открытии списка без
 * явного ?view= в адресе восстанавливает сохранённый срез, чтобы
 * редактор каждый раз начинал с привычного экрана.
 *
 * Возвращает обёртку над сменой среза: применяет его и запоминает.
 */
export function useRememberedView(
    module: string,
    activeView: string,
    apply: (view: string) => void,
): (view: string) => void {
    const user = useAuth();
    const storageKey = `list-view:${module}:${user?.id ?? 'guest'}`;
    const applied = useRef(false);

    useEffect(() => {
        if (applied.current) {
            return;
        }

        applied.current = true;
        const hasExplicitView = new URL(window.location.href).searchParams.has(
            'view',
        );

        if (hasExplicitView) {
            return;
        }

        const saved = localStorage.getItem(storageKey);

        if (saved && saved !== activeView) {
            apply(saved);
        }
        // Только на маунте: activeView/apply меняются при каждом рендере.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    return (view: string) => {
        localStorage.setItem(storageKey, view);
        apply(view);
    };
}
