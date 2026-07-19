import { TriangleAlert, X } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import type { ReactNode } from 'react';
import { createPortal } from 'react-dom';
import { useT } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { Blueprint } from './Blueprint';
import { Button, IconButton } from './Button';
import { Field, Textarea } from './Field';

function useEscape(active: boolean, onClose: () => void) {
    useEffect(() => {
        if (!active) {
            return;
        }

        const handler = (e: KeyboardEvent) => {
            if (e.key === 'Escape') {
                onClose();
            }
        };
        window.addEventListener('keydown', handler);
        document.body.style.overflow = 'hidden';

        return () => {
            window.removeEventListener('keydown', handler);
            document.body.style.overflow = '';
        };
    }, [active, onClose]);
}

export function Modal({
    open,
    onClose,
    title,
    children,
    footer,
    width = 480,
}: {
    open: boolean;
    onClose: () => void;
    title?: ReactNode;
    children: ReactNode;
    footer?: ReactNode;
    width?: number;
}) {
    useEscape(open, onClose);

    if (!open) {
        return null;
    }

    return createPortal(
        <div className="ui-backdrop" onMouseDown={onClose}>
            <Blueprint
                className="ui-dialog"
                style={{ width: `min(${width}px, 100%)` }}
                onMouseDown={(e) => e.stopPropagation()}
                role="dialog"
                aria-modal="true"
            >
                {title && (
                    <div className="ui-dialog-head">
                        <h3 className="ui-dialog-title">{title}</h3>
                        <IconButton label="Закрыть" onClick={onClose}>
                            <X size={18} strokeWidth={1.5} />
                        </IconButton>
                    </div>
                )}
                <div className="ui-dialog-body ui-scroll">{children}</div>
                {footer && <div className="ui-dialog-foot">{footer}</div>}
            </Blueprint>
        </div>,
        document.body,
    );
}

/**
 * Danger confirmation. When `requireComment` is set, the confirm button stays
 * disabled until a reason is entered, and the value is passed to `onConfirm`.
 */
export function ConfirmDialog({
    open,
    onClose,
    onConfirm,
    title,
    body,
    confirmLabel,
    requireComment = false,
    commentLabel,
    danger = true,
    loading = false,
}: {
    open: boolean;
    onClose: () => void;
    onConfirm: (comment: string) => void;
    title: ReactNode;
    body?: ReactNode;
    confirmLabel: string;
    requireComment?: boolean;
    commentLabel?: string;
    danger?: boolean;
    loading?: boolean;
}) {
    const { t } = useT();
    const [comment, setComment] = useState('');
    const [wasOpen, setWasOpen] = useState(open);

    // Reset the comment when the dialog transitions to open (adjust state
    // during render — the pattern React recommends over an effect).
    if (open !== wasOpen) {
        setWasOpen(open);

        if (open) {
            setComment('');
        }
    }

    const blocked = requireComment && comment.trim() === '';

    return (
        <Modal
            open={open}
            onClose={onClose}
            width={460}
            title={
                <span
                    style={{
                        display: 'inline-flex',
                        alignItems: 'center',
                        gap: 9,
                    }}
                >
                    {danger && (
                        <TriangleAlert
                            size={20}
                            strokeWidth={1.5}
                            style={{ color: 'var(--danger)' }}
                        />
                    )}
                    {title}
                </span>
            }
            footer={
                <>
                    <Button variant="secondary" onClick={onClose}>
                        {t('action.cancel')}
                    </Button>
                    <Button
                        variant={danger ? 'danger' : 'primary'}
                        loading={loading}
                        disabled={blocked}
                        onClick={() => onConfirm(comment)}
                    >
                        {confirmLabel}
                    </Button>
                </>
            }
        >
            {body && (
                <p style={{ marginBottom: requireComment ? 14 : 0 }}>{body}</p>
            )}
            {requireComment && (
                <Field
                    label={commentLabel ?? 'Комментарий (обязательно)'}
                    required
                >
                    <Textarea
                        value={comment}
                        onChange={(e) => setComment(e.target.value)}
                        placeholder="Кратко укажите причину…"
                        autoFocus
                        style={{ minHeight: 80 }}
                    />
                </Field>
            )}
        </Modal>
    );
}

export function Drawer({
    open,
    onClose,
    title,
    children,
    footer,
    width = 460,
}: {
    open: boolean;
    onClose: () => void;
    title?: ReactNode;
    children: ReactNode;
    footer?: ReactNode;
    width?: number;
}) {
    useEscape(open, onClose);

    if (!open) {
        return null;
    }

    return createPortal(
        <>
            <div
                className="ui-sidebar-scrim is-open"
                style={{ zIndex: 60 }}
                onClick={onClose}
            />
            <aside
                className="ui-drawer"
                style={{ width: `min(${width}px, 100%)` }}
                role="dialog"
                aria-modal="true"
            >
                {title && (
                    <div className="ui-dialog-head">
                        <h3 className="ui-dialog-title">{title}</h3>
                        <IconButton label="Закрыть" onClick={onClose}>
                            <X size={18} strokeWidth={1.5} />
                        </IconButton>
                    </div>
                )}
                <div className="ui-dialog-body ui-scroll" style={{ flex: 1 }}>
                    {children}
                </div>
                {footer && <div className="ui-dialog-foot">{footer}</div>}
            </aside>
        </>,
        document.body,
    );
}

export interface MenuItem {
    label?: string;
    icon?: ReactNode;
    onSelect?: () => void;
    href?: string;
    danger?: boolean;
    separator?: boolean;
}

/** A click-triggered dropdown menu anchored to its trigger. */
export function Dropdown({
    trigger,
    items,
    align = 'right',
}: {
    trigger: (props: { open: boolean; toggle: () => void }) => ReactNode;
    items: MenuItem[];
    align?: 'left' | 'right';
}) {
    const [open, setOpen] = useState(false);
    const ref = useRef<HTMLDivElement>(null);

    useEffect(() => {
        if (!open) {
            return;
        }

        const handler = (e: MouseEvent) => {
            if (ref.current && !ref.current.contains(e.target as Node)) {
                setOpen(false);
            }
        };
        const esc = (e: KeyboardEvent) => e.key === 'Escape' && setOpen(false);
        window.addEventListener('mousedown', handler);
        window.addEventListener('keydown', esc);

        return () => {
            window.removeEventListener('mousedown', handler);
            window.removeEventListener('keydown', esc);
        };
    }, [open]);

    return (
        <div ref={ref} style={{ position: 'relative', display: 'inline-flex' }}>
            {trigger({ open, toggle: () => setOpen((v) => !v) })}
            {open && (
                <Blueprint
                    corners={false}
                    className="ui-menu"
                    style={{
                        position: 'absolute',
                        top: '100%',
                        marginTop: 4,
                        [align]: 0,
                    }}
                >
                    {items.map((item, i) =>
                        item.separator ? (
                            <div key={i} className="ui-menu-sep" />
                        ) : (
                            <button
                                key={i}
                                type="button"
                                className={cn(
                                    'ui-menu-item',
                                    item.danger && 'is-danger',
                                )}
                                onClick={() => {
                                    setOpen(false);
                                    item.onSelect?.();
                                }}
                            >
                                {item.icon}
                                {item.label}
                            </button>
                        ),
                    )}
                </Blueprint>
            )}
        </div>
    );
}
