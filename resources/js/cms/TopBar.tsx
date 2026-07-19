import { Link, router, usePage } from '@inertiajs/react';
import { Bell, ExternalLink, Menu, Plus, Search } from 'lucide-react';
import { useAuth, useCan } from '@/lib/auth';
import { useT } from '@/lib/i18n';
import { CREATE_ITEMS, NAV } from '@/lib/navigation';
import { Button, IconButton } from '@/ui/Button';
import { Avatar } from '@/ui/Feedback';
import { Dropdown } from '@/ui/Overlay';

export function TopBar({
    unread,
    onOpenSearch,
    onOpenNotifications,
    onOpenSidebar,
}: {
    unread: number;
    onOpenSearch: () => void;
    onOpenNotifications: () => void;
    onOpenSidebar: () => void;
}) {
    const { t, locale } = useT();
    const can = useCan();
    const user = useAuth();
    const url = usePage().url;

    const crumb = resolveCrumb(url, t);
    const createItems = CREATE_ITEMS.filter(
        (i) => !i.permission || can(i.permission),
    ).map((i) => ({
        label: t(i.labelKey),
        onSelect: () => router.visit(i.href),
    }));

    const switchLocale = (next: 'ru' | 'tg') => {
        if (next !== locale) {
            router.post('/locale', { locale: next }, { preserveScroll: true });
        }
    };

    return (
        <header className="ui-topbar">
            <button
                type="button"
                className="ui-btn ui-btn-icon ui-btn-ghost cms-mobile-only"
                aria-label="Меню"
                onClick={onOpenSidebar}
            >
                <Menu size={18} strokeWidth={1.5} />
            </button>

            <div className="ui-breadcrumb">
                <span>{crumb.group}</span>
                <span className="sep">/</span>
                <strong style={{ color: 'var(--color-text)' }}>
                    {crumb.label}
                </strong>
            </div>

            <div style={{ flex: 1 }} />

            <button
                type="button"
                onClick={onOpenSearch}
                className="cms-search-trigger"
                style={{
                    display: 'inline-flex',
                    alignItems: 'center',
                    gap: 8,
                    minWidth: 240,
                    padding: '7px 10px',
                    border: '1px solid var(--color-divider)',
                    background: '#fff',
                    color: 'var(--color-neutral-600)',
                    cursor: 'pointer',
                    fontSize: 13,
                }}
            >
                <Search size={15} strokeWidth={1.5} />
                <span style={{ flex: 1, textAlign: 'left' }}>
                    Поиск по CMS…
                </span>
                <kbd
                    style={{
                        fontSize: 11,
                        border: '1px solid var(--color-divider)',
                        padding: '1px 5px',
                        borderRadius: 3,
                    }}
                >
                    Ctrl K
                </kbd>
            </button>

            {createItems.length > 0 && (
                <Dropdown
                    align="right"
                    trigger={({ toggle }) => (
                        <Button
                            variant="primary"
                            icon={<Plus size={16} strokeWidth={2} />}
                            onClick={toggle}
                        >
                            {t('action.create')}
                        </Button>
                    )}
                    items={createItems}
                />
            )}

            <div style={{ position: 'relative' }}>
                <IconButton
                    label={t('nav.notifications')}
                    onClick={onOpenNotifications}
                >
                    <Bell size={18} strokeWidth={1.5} />
                </IconButton>
                {unread > 0 && (
                    <span
                        style={{
                            position: 'absolute',
                            top: 2,
                            right: 2,
                            minWidth: 16,
                            height: 16,
                            padding: '0 4px',
                            fontSize: 10.5,
                            borderRadius: 8,
                            background: 'var(--danger)',
                            color: '#fff',
                            display: 'grid',
                            placeItems: 'center',
                        }}
                    >
                        {unread}
                    </span>
                )}
            </div>

            <div className="ui-seg cms-desktop-only" style={{ minHeight: 32 }}>
                <button
                    type="button"
                    className={`ui-seg-opt ${locale === 'ru' ? 'is-active' : ''}`}
                    onClick={() => switchLocale('ru')}
                >
                    РУ
                </button>
                <button
                    type="button"
                    className={`ui-seg-opt ${locale === 'tg' ? 'is-active' : ''}`}
                    onClick={() => switchLocale('tg')}
                >
                    ТҶ
                </button>
            </div>

            <a
                href="https://khf.tj"
                target="_blank"
                rel="noopener noreferrer"
                className="ui-btn ui-btn-ghost ui-btn-icon cms-desktop-only"
                title={t('action.open_site')}
            >
                <ExternalLink size={17} strokeWidth={1.5} />
            </a>

            <Link href="/profile" title={user?.name ?? ''}>
                <Avatar
                    initials={user?.initials ?? '—'}
                    size={34}
                    className="cms-topbar-avatar"
                />
            </Link>
        </header>
    );
}

function resolveCrumb(
    url: string,
    t: (k: string) => string,
): { group: string; label: string } {
    const path = url.split('?')[0];

    for (const group of NAV) {
        for (const item of group.items) {
            const base = item.href.split('?')[0];

            if (path === base || path.startsWith(base + '/')) {
                return { group: t(group.labelKey), label: t(item.labelKey) };
            }
        }
    }

    if (path.startsWith('/profile')) {
        return { group: t('nav.group.system'), label: t('nav.profile') };
    }

    return { group: t('app.name'), label: '—' };
}
