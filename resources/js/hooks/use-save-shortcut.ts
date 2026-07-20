import { useEffect } from 'react';

/**
 * Горячая клавиша Ctrl/Cmd+S: перехватывает системное «Сохранить страницу» и
 * вызывает onSave (обычно — сохранение черновика). Клавиша ловится по `code`
 * (`KeyS`), а не по `key`, чтобы срабатывать и на кириллической раскладке.
 * Пока `enabled` = false (например, форма уже отправляется) вызов игнорируется,
 * но default всё равно подавляется — диалог браузера не открывается.
 */
export function useSaveShortcut(onSave: () => void, enabled = true): void {
    useEffect(() => {
        const handler = (e: KeyboardEvent) => {
            if (
                (e.ctrlKey || e.metaKey) &&
                !e.altKey &&
                !e.shiftKey &&
                e.code === 'KeyS'
            ) {
                e.preventDefault();

                if (enabled) {
                    onSave();
                }
            }
        };

        window.addEventListener('keydown', handler);

        return () => window.removeEventListener('keydown', handler);
    }, [onSave, enabled]);
}
