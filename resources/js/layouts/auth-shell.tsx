import { ShieldCheck } from 'lucide-react';
import type { ReactNode } from 'react';
import { initializeTheme } from '@/hooks/use-appearance';
import { I18nProvider } from '@/lib/i18n';

initializeTheme();

function Shell({ children }: { children: ReactNode }) {
    return (
        <div className="ui-authbg">
            <div
                style={{
                    width: 'min(420px, 100%)',
                    display: 'flex',
                    flexDirection: 'column',
                    gap: 20,
                }}
            >
                <div
                    style={{
                        display: 'flex',
                        alignItems: 'center',
                        gap: 14,
                    }}
                >
                    <img
                        src="/logo.webp"
                        alt=""
                        width={56}
                        height={56}
                        style={{ flex: 'none', objectFit: 'contain' }}
                    />
                    <div>
                        <div
                            style={{
                                fontFamily: 'var(--font-heading)',
                                fontWeight: 650,
                                fontSize: 18,
                                letterSpacing: '-0.03em',
                                lineHeight: 1.15,
                            }}
                        >
                            Панель управления сайтом
                        </div>
                        <div
                            style={{
                                fontSize: 13,
                                color: 'var(--color-neutral-600)',
                                marginTop: 2,
                            }}
                        >
                            КЧС и ГО Республики Таджикистан
                        </div>
                    </div>
                </div>

                {children}

                <div
                    style={{
                        display: 'flex',
                        alignItems: 'center',
                        gap: 6,
                        fontSize: 12,
                        color: 'var(--color-neutral-600)',
                    }}
                >
                    <ShieldCheck size={14} strokeWidth={1.5} />
                    Защищённое соединение · Все действия фиксируются
                </div>
            </div>
        </div>
    );
}

export default function AuthShell({ children }: { children: ReactNode }) {
    return (
        <I18nProvider>
            <Shell>{children}</Shell>
        </I18nProvider>
    );
}
