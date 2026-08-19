import { TriangleAlert, X } from 'lucide-react';
import { useEffect, useId, useLayoutEffect, useRef, useState } from 'react';
import type { ReactNode } from 'react';
import { createPortal } from 'react-dom';
import { useDialogFocus } from '@/hooks/use-dialog-focus';
import { useT } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { Blueprint } from './Blueprint';
import { Button, IconButton } from './Button';
import { Field, Textarea } from './Field';

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
    const dialogRef = useDialogFocus<HTMLDivElement>(open, onClose);
    const titleId = useId();

    if (!open) {
        return null;
    }

    return createPortal(
        <div className="ui-backdrop" onMouseDown={onClose}>
            <Blueprint
                ref={dialogRef}
                className="ui-dialog"
                style={{ width: `min(${width}px, 100%)` }}
                onMouseDown={(e) => e.stopPropagation()}
                role="dialog"
                aria-modal="true"
                aria-labelledby={title ? titleId : undefined}
                aria-label={title ? undefined : 'Диалог'}
                tabIndex={-1}
            >
                {title && (
                    <div className="ui-dialog-head">
                        <h3 id={titleId} className="ui-dialog-title">
                            {title}
                        </h3>
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
    const dialogRef = useDialogFocus<HTMLElement>(open, onClose);
    const titleId = useId();

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
                ref={dialogRef}
                className="ui-drawer"
                style={{ width: `min(${width}px, 100%)` }}
                role="dialog"
                aria-modal="true"
                aria-labelledby={title ? titleId : undefined}
                aria-label={title ? undefined : 'Панель'}
                tabIndex={-1}
            >
                {title && (
                    <div className="ui-dialog-head">
                        <h3 id={titleId} className="ui-dialog-title">
                            {title}
                        </h3>
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
    description?: string;
    icon?: ReactNode;
    onSelect?: () => void;
    href?: string;
    danger?: boolean;
    separator?: boolean;
}

/** A click-triggered menu, portaled so sticky bars cannot clip it. */
export function Dropdown({
    trigger,
    items,
    align = 'right',
    placement = 'auto',
}: {
    trigger: (props: { open: boolean; toggle: () => void }) => ReactNode;
    items: MenuItem[];
    align?: 'left' | 'right';
    placement?: 'top' | 'bottom' | 'auto';
}) {
    const [open, setOpen] = useState(false);
    const [ready, setReady] = useState(false);
    const [coords, setCoords] = useState({ top: 0, left: 0 });
    const triggerRef = useRef<HTMLDivElement>(null);
    const menuRef = useRef<HTMLDivElement>(null);

    const updatePosition = () => {
        const triggerEl = triggerRef.current;
        const menuEl = menuRef.current;

        if (!triggerEl || !menuEl) {
            return;
        }

        const rect = triggerEl.getBoundingClientRect();
        const menu = menuEl.getBoundingClientRect();
        const gap = 8;
        const spaceBelow = window.innerHeight - rect.bottom;
        const openUp =
            placement === 'top' ||
            (placement === 'auto' && spaceBelow < menu.height + gap + 12);
        let top = openUp ? rect.top - menu.height - gap : rect.bottom + gap;
        let left = align === 'right' ? rect.right - menu.width : rect.left;

        left = Math.min(Math.max(8, left), window.innerWidth - menu.width - 8);
        top = Math.min(Math.max(8, top), window.innerHeight - menu.height - 8);

        setCoords({ top, left });
        setReady(true);
    };

    useLayoutEffect(() => {
        if (!open) {
            setReady(false);

            return;
        }

        updatePosition();
        window.addEventListener('resize', updatePosition);
        window.addEventListener('scroll', updatePosition, true);

        return () => {
            window.removeEventListener('resize', updatePosition);
            window.removeEventListener('scroll', updatePosition, true);
        };
    }, [align, open, placement]);

    useEffect(() => {
        if (!open) {
            return;
        }

        const handler = (e: MouseEvent) => {
            const target = e.target as Node;

            if (
                triggerRef.current?.contains(target) ||
                menuRef.current?.contains(target)
            ) {
                return;
            }

            setOpen(false);
        };
        const esc = (e: KeyboardEvent) => {
            if (e.key === 'Escape') {
                setOpen(false);
            }
        };
        window.addEventListener('mousedown', handler);
        window.addEventListener('keydown', esc);

        return () => {
            window.removeEventListener('mousedown', handler);
            window.removeEventListener('keydown', esc);
        };
    }, [open]);

    return (
        <div
            ref={triggerRef}
            style={{ position: 'relative', display: 'inline-flex' }}
        >
            {trigger({ open, toggle: () => setOpen((v) => !v) })}
            {open &&
                createPortal(
                    <Blueprint
                        ref={menuRef}
                        corners={false}
                        className="ui-menu"
                        role="menu"
                        style={{
                            top: coords.top,
                            left: coords.left,
                            visibility: ready ? 'visible' : 'hidden',
                        }}
                    >
                        {items.map((item, i) =>
                            item.separator ? (
                                <div key={i} className="ui-menu-sep" />
                            ) : (
                                <button
                                    key={i}
                                    type="button"
                                    role="menuitem"
                                    className={cn(
                                        'ui-menu-item',
                                        item.description && 'has-copy',
                                        item.danger && 'is-danger',
                                    )}
                                    onClick={() => {
                                        setOpen(false);
                                        item.onSelect?.();
                                    }}
                                >
                                    {item.icon}
                                    {item.description ? (
                                        <span className="ui-menu-item-copy">
                                            <strong>{item.label}</strong>
                                            <span>{item.description}</span>
                                        </span>
                                    ) : (
                                        item.label
                                    )}
                                </button>
                            ),
                        )}
                    </Blueprint>,
                    document.body,
                )}
        </div>
    );
}
