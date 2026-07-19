import { useCallback, useEffect, useRef, useState } from 'react';
import type { ReactNode } from 'react';
import { AppSidebar } from '@/cms/AppSidebar';
import { CommandPalette } from '@/cms/CommandPalette';
import { NotificationCenter } from '@/cms/NotificationCenter';
import { TopBar } from '@/cms/TopBar';
import { useShared } from '@/lib/auth';
import { I18nProvider } from '@/lib/i18n';
import { ToastProvider, useToast } from '@/ui/Toast';

function setCookie(name: string, value: string) {
    document.cookie = `${name}=${value};path=/;max-age=31536000;samesite=lax`;
}

/** Turns server flash messages into toasts. Must live under ToastProvider. */
function FlashToasts() {
    const { flash } = useShared();
    const toast = useToast();
    const last = useRef('');

    useEffect(() => {
        const key = JSON.stringify(flash);

        if (key === last.current) {
            return;
        }

        last.current = key;

        if (flash.success) {
            toast(flash.success, 'success');
        }

        if (flash.error) {
            toast(flash.error, 'error');
        }

        if (flash.warning) {
            toast(flash.warning, 'warning');
        }

        if (flash.info) {
            toast(flash.info, 'info');
        }
    }, [flash, toast]);

    return null;
}

function LayoutInner({ children }: { children: ReactNode }) {
    const shared = useShared();
    const [collapsed, setCollapsed] = useState(!(shared.sidebarOpen ?? true));
    const [mobileOpen, setMobileOpen] = useState(false);
    const [notifOpen, setNotifOpen] = useState(false);
    const [paletteOpen, setPaletteOpen] = useState(false);

    const toggleCollapse = useCallback(() => {
        setCollapsed((c) => {
            setCookie('sidebar_state', c ? 'true' : 'false');

            return !c;
        });
    }, []);

    useEffect(() => {
        const handler = (e: KeyboardEvent) => {
            if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'k') {
                e.preventDefault();
                setPaletteOpen((v) => !v);
            }
        };
        window.addEventListener('keydown', handler);

        return () => window.removeEventListener('keydown', handler);
    }, []);

    return (
        <div className="ui-shell">
            <AppSidebar
                collapsed={collapsed}
                mobileOpen={mobileOpen}
                badges={shared.nav_badges}
                onToggleCollapse={toggleCollapse}
                onCloseMobile={() => setMobileOpen(false)}
            />
            <div className="ui-main">
                <TopBar
                    unread={shared.notifications.unread}
                    onOpenSearch={() => setPaletteOpen(true)}
                    onOpenNotifications={() => setNotifOpen(true)}
                    onOpenSidebar={() => setMobileOpen(true)}
                />
                <main className="ui-content ui-scroll">{children}</main>
            </div>

            <NotificationCenter
                open={notifOpen}
                onClose={() => setNotifOpen(false)}
            />
            <CommandPalette
                open={paletteOpen}
                onClose={() => setPaletteOpen(false)}
            />
            <FlashToasts />
        </div>
    );
}

export default function CmsLayout({ children }: { children: ReactNode }) {
    return (
        <I18nProvider>
            <ToastProvider>
                <LayoutInner>{children}</LayoutInner>
            </ToastProvider>
        </I18nProvider>
    );
}
