import { useEffect, useRef } from 'react';
import type { RefObject } from 'react';

const FOCUSABLE_SELECTOR = [
    'a[href]',
    'button:not([disabled])',
    'input:not([disabled]):not([type="hidden"])',
    'select:not([disabled])',
    'textarea:not([disabled])',
    '[contenteditable="true"]',
    '[tabindex]:not([tabindex="-1"])',
].join(',');

let bodyScrollLocks = 0;
let previousBodyOverflow = '';

function focusableElements(container: HTMLElement): HTMLElement[] {
    return Array.from(
        container.querySelectorAll<HTMLElement>(FOCUSABLE_SELECTOR),
    ).filter(
        (element) =>
            element.getAttribute('aria-hidden') !== 'true' &&
            element.getClientRects().length > 0,
    );
}

export function useDialogFocus<T extends HTMLElement>(
    active: boolean,
    onClose: () => void,
): RefObject<T | null> {
    const containerRef = useRef<T>(null);
    const returnFocusRef = useRef<HTMLElement | null>(null);
    const onCloseRef = useRef(onClose);

    useEffect(() => {
        onCloseRef.current = onClose;
    }, [onClose]);

    useEffect(() => {
        if (!active) {
            return;
        }

        const container = containerRef.current;

        if (!container) {
            return;
        }

        returnFocusRef.current =
            document.activeElement instanceof HTMLElement
                ? document.activeElement
                : null;

        if (bodyScrollLocks === 0) {
            previousBodyOverflow = document.body.style.overflow;
        }

        bodyScrollLocks += 1;
        document.body.style.overflow = 'hidden';

        const isTopmostDialog = (): boolean => {
            const dialogs = document.querySelectorAll<HTMLElement>(
                '[role="dialog"][aria-modal="true"]',
            );

            return dialogs.item(dialogs.length - 1) === container;
        };

        const handleKeyDown = (event: KeyboardEvent) => {
            if (!isTopmostDialog()) {
                return;
            }

            if (event.key === 'Escape') {
                event.preventDefault();
                onCloseRef.current();

                return;
            }

            if (event.key !== 'Tab') {
                return;
            }

            const focusable = focusableElements(container);

            if (focusable.length === 0) {
                event.preventDefault();
                container.focus();

                return;
            }

            const first = focusable[0];
            const last = focusable[focusable.length - 1];

            if (event.shiftKey && document.activeElement === first) {
                event.preventDefault();
                last.focus();
            } else if (!event.shiftKey && document.activeElement === last) {
                event.preventDefault();
                first.focus();
            }
        };

        document.addEventListener('keydown', handleKeyDown);

        const focusFrame = window.requestAnimationFrame(() => {
            if (!container.contains(document.activeElement)) {
                (focusableElements(container)[0] ?? container).focus();
            }
        });

        return () => {
            window.cancelAnimationFrame(focusFrame);
            document.removeEventListener('keydown', handleKeyDown);
            bodyScrollLocks = Math.max(0, bodyScrollLocks - 1);

            if (bodyScrollLocks === 0) {
                document.body.style.overflow = previousBodyOverflow;
            }

            if (returnFocusRef.current?.isConnected) {
                returnFocusRef.current.focus();
            }
        };
    }, [active]);

    return containerRef;
}
