import { createInertiaApp } from '@inertiajs/react';
import AuthShell from '@/layouts/auth-shell';
import CmsLayout from '@/layouts/cms-layout';

const appName = import.meta.env.VITE_APP_NAME || 'КЧС РТ · CMS';

createInertiaApp({
    title: (title) => (title ? `${title} — ${appName}` : appName),
    layout: (name) => {
        switch (true) {
            case name === 'welcome':
                return null;
            case name.startsWith('auth/'):
                return AuthShell;
            default:
                return CmsLayout;
        }
    },
    strictMode: true,
    progress: {
        color: '#5980a6',
    },
});
