import { useState, useSyncExternalStore } from 'react';

/**
 * Wide enough for the editor's settings panel to sit beside the canvas; below
 * it the panel covers the text (`.wp-inspector`, components.css).
 */
const SIDE_BY_SIDE = '(min-width: 1025px)';

function subscribe(onChange: () => void): () => void {
    const query = window.matchMedia(SIDE_BY_SIDE);
    query.addEventListener('change', onChange);

    return () => query.removeEventListener('change', onChange);
}

/**
 * Whether the editor's settings panel is open. As in WordPress, it opens
 * beside the canvas on a wide screen and starts closed on a tablet or phone,
 * where it would cover the text; «Настройки» toggles it. Once toggled, the
 * editor's choice stays. The server renders it open.
 */
export function useInspectorOpen(): [boolean, (open: boolean) => void] {
    const sideBySide = useSyncExternalStore(
        subscribe,
        () => window.matchMedia(SIDE_BY_SIDE).matches,
        () => true,
    );
    const [choice, setChoice] = useState<boolean | null>(null);

    return [choice ?? sideBySide, setChoice];
}
