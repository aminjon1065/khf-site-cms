import { router } from '@inertiajs/react';
import { ShieldCheck } from 'lucide-react';
import type { ReactNode } from 'react';
import { initializeTheme } from '@/hooks/use-appearance';
import { I18nProvider, useT } from '@/lib/i18n';

initializeTheme();
import { locale as localeRoute } from '@/routes';

function Shell({ children }: { children: ReactNode }) {
    const { locale } = useT();

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
                            Control Panel
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
                        justifyContent: 'space-between',
                        gap: 12,
                        flexWrap: 'wrap',
                        fontSize: 12,
                        color: 'var(--color-neutral-600)',
                    }}
                >
                    <span
                        style={{
                            display: 'inline-flex',
                            alignItems: 'center',
                            gap: 6,
                        }}
                    >
                        <ShieldCheck size={14} strokeWidth={1.5} />
                        Защищённое соединение · Все действия фиксируются
                    </span>
                    <span style={{ display: 'inline-flex', gap: 8 }}>
                        <button
                            type="button"
                            onClick={() => switchLocale('tg')}
                            style={langLink(locale === 'tg')}
                        >
                            Тоҷикӣ
                        </button>
                        <button
                            type="button"
                            onClick={() => switchLocale('ru')}
                            style={langLink(locale === 'ru')}
                        >
                            Русский
                        </button>
                        <span style={{ color: 'var(--color-neutral-400)' }}>
                            English
                        </span>
                    </span>
                </div>
            </div>
        </div>
    );
}

function langLink(active: boolean): React.CSSProperties {
    return {
        border: 0,
        background: 'transparent',
        cursor: 'pointer',
        fontSize: 12,
        color: active ? 'var(--brand-700)' : 'var(--color-neutral-600)',
        fontWeight: active ? 700 : 400,
    };
}

export default function AuthShell({ children }: { children: ReactNode }) {
    return (
        <I18nProvider>
            <Shell>{children}</Shell>
        </I18nProvider>
    );
}
