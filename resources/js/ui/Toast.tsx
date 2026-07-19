import { Check, Info, TriangleAlert, X } from 'lucide-react';
import {
    createContext,
    useCallback,
    useContext,
    useEffect,
    useRef,
    useState,
} from 'react';
import type { ReactNode } from 'react';
import { createPortal } from 'react-dom';
import { cn } from '@/lib/utils';

type ToastTone = 'success' | 'error' | 'warning' | 'info';

interface ToastItem {
    id: number;
    message: string;
    tone: ToastTone;
}

const ToastContext = createContext<(message: string, tone?: ToastTone) => void>(
    () => {},
);

let counter = 0;

export function ToastProvider({ children }: { children: ReactNode }) {
    const [toasts, setToasts] = useState<ToastItem[]>([]);
    const timers = useRef<Record<number, ReturnType<typeof setTimeout>>>({});

    const remove = useCallback((id: number) => {
        setToasts((list) => list.filter((t) => t.id !== id));
        delete timers.current[id];
    }, []);

    const push = useCallback(
        (message: string, tone: ToastTone = 'success') => {
            const id = ++counter;
            setToasts((list) => [...list, { id, message, tone }]);
            timers.current[id] = setTimeout(() => remove(id), 3200);
        },
        [remove],
    );

    useEffect(() => {
        const stored = timers.current;

        return () => {
            Object.values(stored).forEach(clearTimeout);
        };
    }, []);

    return (
        <ToastContext.Provider value={push}>
            {children}
            {createPortal(
                <div className="ui-toast-wrap">
                    {toasts.map((t) => (
                        <div
                            key={t.id}
                            className={cn('ui-toast', `is-${t.tone}`)}
                            role="status"
                        >
                            <ToastIcon tone={t.tone} />
                            <span style={{ flex: 1 }}>{t.message}</span>
                            <button
                                type="button"
                                aria-label="Закрыть"
                                onClick={() => remove(t.id)}
                                style={{
                                    border: 0,
                                    background: 'transparent',
                                    cursor: 'pointer',
                                    color: 'inherit',
                                }}
                            >
                                <X size={15} strokeWidth={1.5} />
                            </button>
                        </div>
                    ))}
                </div>,
                document.body,
            )}
        </ToastContext.Provider>
    );
}

function ToastIcon({ tone }: { tone: ToastTone }) {
    const color =
        tone === 'success'
            ? 'var(--ok)'
            : tone === 'error'
              ? 'var(--danger)'
              : tone === 'warning'
                ? 'var(--warn)'
                : 'var(--color-accent)';
    const props = {
        size: 17,
        strokeWidth: 1.5,
        style: { color, flex: 'none' as const },
    };

    if (tone === 'success') {
        return <Check {...props} />;
    }

    if (tone === 'error' || tone === 'warning') {
        return <TriangleAlert {...props} />;
    }

    return <Info {...props} />;
}

export function useToast() {
    return useContext(ToastContext);
}
