import { Link, router, usePage } from '@inertiajs/react';
import {
    Bell,
    ExternalLink,
    Menu,
    Monitor,
    Moon,
    Plus,
    Search,
    Sun,
} from 'lucide-react';
import { edit as editProfile } from '@/actions/App/Http/Controllers/Settings/ProfileController';
import type { Appearance } from '@/hooks/use-appearance';
import { useAppearance } from '@/hooks/use-appearance';
import { useAuth, useCan } from '@/lib/auth';
import { useT } from '@/lib/i18n';
import { CREATE_ITEMS, NAV } from '@/lib/navigation';
import { locale as localeRoute } from '@/routes';
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

    const { appearance, updateAppearance } = useAppearance();
    const crumb = resolveCrumb(url, t);
    const createItems = CREATE_ITEMS.filter(
        (i) => !i.permission || can(i.permission),
    ).map((i) => ({
        label: t(i.labelKey),
        onSelect: () => router.visit(i.href),
    }));

    const switchLocale = (next: 'ru' | 'tg') => {
        if (next !== locale) {
            router.post(
                localeRoute.url(),
                { locale: next },
                { preserveScroll: true },
            );
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
                <strong>{crumb.label}</strong>
            </div>

            <div className="cms-topbar-spacer" />

            <button
                type="button"
                onClick={onOpenSearch}
                className="cms-search-trigger"
                aria-label="Поиск по CMS"
            >
                <Search size={15} strokeWidth={1.5} />
                <span className="cms-search-trigger-label">Поиск по CMS…</span>
                <kbd className="cms-search-trigger-shortcut">Ctrl K</kbd>
            </button>

            {createItems.length > 0 && (
                <Dropdown
                    align="right"
                    trigger={({ open, toggle }) => (
                        <Button
                            variant="primary"
                            icon={<Plus size={16} strokeWidth={2} />}
                            className="cms-create-trigger"
                            aria-label={t('action.create')}
                            aria-expanded={open}
                            onClick={toggle}
                        >
                            <span className="cms-create-trigger-label">
                                {t('action.create')}
                            </span>
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

            <IconButton
                label={themeLabel(appearance)}
                className="cms-desktop-only"
                onClick={() => updateAppearance(nextTheme(appearance))}
            >
                {appearance === 'dark' ? (
                    <Moon size={17} strokeWidth={1.5} />
                ) : appearance === 'system' ? (
                    <Monitor size={17} strokeWidth={1.5} />
                ) : (
                    <Sun size={17} strokeWidth={1.5} />
                )}
            </IconButton>

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

            <Link
                href={editProfile.url()}
                title={user?.name ?? ''}
                className="cms-topbar-profile"
            >
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

function nextTheme(current: Appearance): Appearance {
    if (current === 'light') {
        return 'dark';
    }

    if (current === 'dark') {
        return 'system';
    }

    return 'light';
}

function themeLabel(current: Appearance): string {
    if (current === 'dark') {
        return 'Тема: тёмная. Нажмите, чтобы выбрать системную.';
    }

    if (current === 'system') {
        return 'Тема: как в системе. Нажмите, чтобы выбрать светлую.';
    }

    return 'Тема: светлая. Нажмите, чтобы выбрать тёмную.';
}
