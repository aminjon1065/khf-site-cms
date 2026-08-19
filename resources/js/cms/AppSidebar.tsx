import { Link, usePage } from '@inertiajs/react';
import { ChevronsLeft, Search, ShieldAlert } from 'lucide-react';
import { useCan } from '@/lib/auth';
import { useT } from '@/lib/i18n';
import { NAV } from '@/lib/navigation';
import type { NavItem } from '@/lib/navigation';
import { cn } from '@/lib/utils';
import type { NavBadges } from '@/types/cms';

export function AppSidebar({
    collapsed,
    mobileOpen,
    badges,
    onToggleCollapse,
    onCloseMobile,
    onOpenSearch,
}: {
    collapsed: boolean;
    mobileOpen: boolean;
    badges: NavBadges;
    onToggleCollapse: () => void;
    onCloseMobile: () => void;
    onOpenSearch: () => void;
}) {
    const { t } = useT();
    const can = useCan();
    const url = usePage().url;

    const isActive = (item: NavItem) => {
        const base = item.href.split('?')[0];

        return (
            url === base ||
            (base !== '/dashboard' && url.startsWith(base + '/')) ||
            url.startsWith(base)
        );
    };

    return (
        <>
            <div
                className={cn('ui-sidebar-scrim', mobileOpen && 'is-open')}
                onClick={onCloseMobile}
            />
            <aside
                className={cn(
                    'ui-sidebar',
                    collapsed && 'is-collapsed',
                    mobileOpen && 'is-mobile-open',
                )}
            >
                <div className="ui-sidebar-brand">
                    <span className="mark">
                        <ShieldAlert size={18} strokeWidth={1.75} />
                    </span>
                    <span className="brand-text">
                        <span className="brand-name">КЧС</span>
                        <span className="brand-sub">Control Panel</span>
                    </span>
                </div>

                <button
                    type="button"
                    className="ui-sidebar-search"
                    onClick={onOpenSearch}
                    aria-label="Найти в панели"
                    title={collapsed ? 'Поиск' : undefined}
                >
                    <Search size={16} strokeWidth={1.75} />
                    <span className="label">Поиск…</span>
                    <kbd>Ctrl K</kbd>
                </button>

                <nav className="ui-sidebar-nav ui-scroll">
                    {NAV.map((group) => {
                        const items = group.items.filter(
                            (i) => !i.permission || can(i.permission),
                        );

                        if (items.length === 0) {
                            return null;
                        }

                        return (
                            <div key={group.labelKey}>
                                <div className="ui-nav-group">
                                    {t(group.labelKey)}
                                </div>
                                {items.map((item) => {
                                    const Icon = item.icon;
                                    const badge = item.badge
                                        ? badges[item.badge]
                                        : undefined;

                                    return (
                                        <Link
                                            key={item.key}
                                            href={item.href}
                                            onClick={onCloseMobile}
                                            className={cn(
                                                'ui-nav-item',
                                                isActive(item) && 'is-active',
                                            )}
                                            title={
                                                collapsed
                                                    ? t(item.labelKey)
                                                    : undefined
                                            }
                                        >
                                            <Icon size={17} strokeWidth={1.5} />
                                            <span className="label">
                                                {t(item.labelKey)}
                                            </span>
                                            {badge ? (
                                                <span
                                                    className={cn(
                                                        'ui-nav-badge',
                                                        item.badge ===
                                                            'alerts' &&
                                                            'is-warn',
                                                    )}
                                                >
                                                    {badge}
                                                </span>
                                            ) : null}
                                        </Link>
                                    );
                                })}
                            </div>
                        );
                    })}
                </nav>

                <div className="ui-sidebar-foot">
                    <button
                        type="button"
                        onClick={onToggleCollapse}
                        className="ui-nav-item"
                        style={{ width: '100%' }}
                        title={t('Свернуть панель')}
                    >
                        <ChevronsLeft
                            size={17}
                            strokeWidth={1.5}
                            style={{
                                transform: collapsed
                                    ? 'rotate(180deg)'
                                    : undefined,
                            }}
                        />
                        <span className="label">Свернуть панель</span>
                    </button>
                </div>
            </aside>
        </>
    );
}
