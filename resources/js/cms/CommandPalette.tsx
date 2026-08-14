import { router } from '@inertiajs/react';
import { Command } from 'cmdk';
import { CornerDownLeft, Plus, Search } from 'lucide-react';
import { createPortal } from 'react-dom';
import { useDialogFocus } from '@/hooks/use-dialog-focus';
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
    const dialogRef = useDialogFocus<HTMLDivElement>(open, onClose);

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
            ref={dialogRef}
            onMouseDown={onClose}
            role="dialog"
            aria-modal="true"
            aria-label="Командная палитра"
            tabIndex={-1}
            className="cms-cmd-backdrop"
        >
            <Blueprint
                corners={false}
                onMouseDown={(e) => e.stopPropagation()}
                className="cms-cmd-panel"
            >
                <Command label="Командная палитра">
                    <div className="cms-cmd-head">
                        <Search
                            size={17}
                            strokeWidth={1.5}
                            style={{ color: 'var(--color-neutral-500)' }}
                        />
                        <Command.Input
                            placeholder="Команда или поиск: предупреждения, новости, документы, пользователи…"
                            className="cms-cmd-input"
                        />
                        <kbd className="cms-cmd-esc">Esc</kbd>
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
