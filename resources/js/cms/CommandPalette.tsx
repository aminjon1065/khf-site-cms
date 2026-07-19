import { router } from '@inertiajs/react';
import { Command } from 'cmdk';
import { CornerDownLeft, Plus, Search } from 'lucide-react';
import { useEffect } from 'react';
import { createPortal } from 'react-dom';
import { useCan } from '@/lib/auth';
import { useT } from '@/lib/i18n';
import { CREATE_ITEMS, NAV } from '@/lib/navigation';
import { Blueprint } from '@/ui/Blueprint';

export function CommandPalette({
    open,
    onClose,
}: {
    open: boolean;
    onClose: () => void;
}) {
    const { t } = useT();
    const can = useCan();

    useEffect(() => {
        if (!open) {
            return;
        }

        const esc = (e: KeyboardEvent) => e.key === 'Escape' && onClose();
        window.addEventListener('keydown', esc);
        document.body.style.overflow = 'hidden';

        return () => {
            window.removeEventListener('keydown', esc);
            document.body.style.overflow = '';
        };
    }, [open, onClose]);

    if (!open) {
        return null;
    }

    const go = (href: string) => {
        onClose();
        router.visit(href);
    };

    const createItems = CREATE_ITEMS.filter(
        (i) => !i.permission || can(i.permission),
    );
    const navItems = NAV.flatMap((g) =>
        g.items
            .filter((i) => !i.permission || can(i.permission))
            .map((i) => ({ ...i, group: t(g.labelKey) })),
    );

    return createPortal(
        <div
            onMouseDown={onClose}
            style={{
                position: 'fixed',
                inset: 0,
                zIndex: 90,
                background:
                    'color-mix(in srgb, var(--color-neutral-900) 42%, transparent)',
                display: 'flex',
                justifyContent: 'center',
                alignItems: 'flex-start',
                paddingTop: '14vh',
            }}
        >
            <Blueprint
                corners={false}
                onMouseDown={(e) => e.stopPropagation()}
                style={{
                    width: 'min(560px, 92vw)',
                    background: '#fbfbfc',
                    boxShadow: 'var(--shadow-lg)',
                }}
            >
                <Command label="Командная палитра">
                    <div
                        style={{
                            display: 'flex',
                            alignItems: 'center',
                            gap: 9,
                            padding: '12px 14px',
                            borderBottom: '1px solid var(--color-divider)',
                        }}
                    >
                        <Search
                            size={17}
                            strokeWidth={1.5}
                            style={{ color: 'var(--color-neutral-500)' }}
                        />
                        <Command.Input
                            autoFocus
                            placeholder="Команда или поиск: предупреждения, новости, документы, пользователи…"
                            className="cms-cmd-input"
                            style={{
                                flex: 1,
                                border: 0,
                                outline: 'none',
                                background: 'transparent',
                                fontSize: 14,
                                fontFamily: 'var(--font-body)',
                            }}
                        />
                        <kbd
                            style={{
                                fontSize: 11,
                                border: '1px solid var(--color-divider)',
                                padding: '1px 5px',
                                borderRadius: 3,
                                color: 'var(--color-neutral-500)',
                            }}
                        >
                            Esc
                        </kbd>
                    </div>
                    <Command.List
                        style={{ maxHeight: 360, overflow: 'auto', padding: 6 }}
                        className="ui-scroll"
                    >
                        <Command.Empty
                            style={{
                                padding: '18px 12px',
                                fontSize: 13,
                                color: 'var(--color-neutral-500)',
                            }}
                        >
                            Ничего не найдено
                        </Command.Empty>

                        <Command.Group
                            heading={t('action.create')}
                            className="cms-cmd-group"
                        >
                            {createItems.map((i) => (
                                <Command.Item
                                    key={`c-${i.key}`}
                                    onSelect={() => go(i.href)}
                                    className="cms-cmd-item"
                                >
                                    <Plus size={15} strokeWidth={1.5} />
                                    <span style={{ flex: 1 }}>
                                        {t('action.create')} —{' '}
                                        {t(i.labelKey).toLowerCase()}
                                    </span>
                                    <CornerDownLeft
                                        size={13}
                                        strokeWidth={1.5}
                                        style={{ opacity: 0.4 }}
                                    />
                                </Command.Item>
                            ))}
                        </Command.Group>

                        <Command.Group
                            heading="Навигация"
                            className="cms-cmd-group"
                        >
                            {navItems.map((i) => {
                                const Icon = i.icon;

                                return (
                                    <Command.Item
                                        key={`n-${i.key}`}
                                        value={`${t(i.labelKey)} ${i.group}`}
                                        onSelect={() => go(i.href)}
                                        className="cms-cmd-item"
                                    >
                                        <Icon size={15} strokeWidth={1.5} />
                                        <span style={{ flex: 1 }}>
                                            {t(i.labelKey)}
                                        </span>
                                        <span
                                            style={{
                                                fontSize: 11.5,
                                                color: 'var(--color-neutral-500)',
                                            }}
                                        >
                                            {i.group}
                                        </span>
                                    </Command.Item>
                                );
                            })}
                        </Command.Group>
                    </Command.List>
                </Command>
            </Blueprint>
        </div>,
        document.body,
    );
}
