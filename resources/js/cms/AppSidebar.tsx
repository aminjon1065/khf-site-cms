import { Link, usePage } from '@inertiajs/react';
import { ChevronDown, ChevronsLeft, Search } from 'lucide-react';
import { useOpenNavGroups } from '@/hooks/use-open-nav-groups';
import { useCan } from '@/lib/auth';
import { useT } from '@/lib/i18n';
import { NAV, navItemAllowed } from '@/lib/navigation';
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
    const [openGroups, toggleGroup] = useOpenNavGroups();

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
                    <img
                        className="mark"
                        src="/logo.webp"
                        alt=""
                        width={32}
                        height={32}
                    />
                    <span className="brand-text">
                        <span className="brand-name">КЧС</span>
                        <span className="brand-sub">Панель управления</span>
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
                        const items = group.items.filter((i) =>
                            navItemAllowed(i, can),
                        );

                        if (items.length === 0) {
                            return null;
                        }

                        // The group of the current page stays open; an
                        // icon-only sidebar shows every item.
                        const hasActive = items.some(isActive);
                        const folded =
                            Boolean(group.collapsible) &&
                            !collapsed &&
                            !hasActive &&
                            !openGroups.includes(group.labelKey);
                        const listId = `nav-${group.labelKey.replace(/\W+/g, '-')}`;

                        return (
                            <div key={group.labelKey}>
                                {group.collapsible ? (
                                    <button
                                        type="button"
                                        className="ui-nav-group ui-nav-group-toggle"
                                        aria-expanded={!folded}
                                        aria-controls={listId}
                                        disabled={hasActive}
                                        onClick={() =>
                                            toggleGroup(group.labelKey)
                                        }
                                    >
                                        <span>{t(group.labelKey)}</span>
                                        <ChevronDown
                                            size={14}
                                            strokeWidth={1.75}
                                            aria-hidden
                                            className={cn(
                                                'chevron',
                                                folded && 'is-folded',
                                            )}
                                        />
                                    </button>
                                ) : (
                                    <div className="ui-nav-group">
                                        {t(group.labelKey)}
                                    </div>
                                )}
                                <div id={listId} hidden={folded}>
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
                                                    isActive(item) &&
                                                        'is-active',
                                                )}
                                                title={
                                                    collapsed
                                                        ? t(item.labelKey)
                                                        : undefined
                                                }
                                            >
                                                <Icon
                                                    size={17}
                                                    strokeWidth={1.5}
                                                />
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
