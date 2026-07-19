import { Link, usePage } from '@inertiajs/react';
import { ChevronsLeft, ShieldAlert } from 'lucide-react';
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
}: {
    collapsed: boolean;
    mobileOpen: boolean;
    badges: NavBadges;
    onToggleCollapse: () => void;
    onCloseMobile: () => void;
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
                    <span className="brand-text" style={{ lineHeight: 1.2 }}>
                        <span
                            style={{
                                display: 'block',
                                fontFamily: 'var(--font-heading)',
                                fontWeight: 600,
                                fontSize: 15,
                            }}
                        >
                            КЧС Таджикистана
                        </span>
                        <span
                            style={{
                                display: 'block',
                                fontSize: 10.5,
                                color: 'rgba(255,255,255,.55)',
                            }}
                        >
                            Система управления сайтом
                        </span>
                    </span>
                </div>

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

                <div
                    style={{
                        borderTop: '1px solid rgba(255,255,255,.08)',
                        padding: 8,
                    }}
                >
                    <button
                        type="button"
                        onClick={onToggleCollapse}
                        className="ui-nav-item"
                        style={{ width: '100%', borderLeft: 'none' }}
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
