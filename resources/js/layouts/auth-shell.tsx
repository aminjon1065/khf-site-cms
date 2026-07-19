import { router } from '@inertiajs/react';
import { ShieldAlert, ShieldCheck } from 'lucide-react';
import type { ReactNode } from 'react';
import { I18nProvider, useT } from '@/lib/i18n';
import { Blueprint } from '@/ui/Blueprint';

function Shell({ children }: { children: ReactNode }) {
    const { locale } = useT();

    const switchLocale = (next: 'ru' | 'tg') => {
        if (next !== locale) {
            router.post('/locale', { locale: next }, { preserveScroll: true });
        }
    };

    return (
        <div className="ui-authbg">
            <div
                style={{
                    width: 'min(400px, 100%)',
                    display: 'flex',
                    flexDirection: 'column',
                    gap: 20,
                }}
            >
                <div style={{ display: 'flex', alignItems: 'center', gap: 14 }}>
                    <Blueprint
                        corners={false}
                        style={{
                            width: 52,
                            height: 52,
                            background: 'var(--color-accent-900)',
                            display: 'grid',
                            placeItems: 'center',
                            color: '#fff',
                            flex: 'none',
                        }}
                    >
                        <ShieldAlert size={26} strokeWidth={1.6} />
                    </Blueprint>
                    <div>
                        <div
                            style={{
                                fontFamily: 'var(--font-heading)',
                                fontWeight: 600,
                                fontSize: 19,
                                lineHeight: 1.15,
                            }}
                        >
                            КЧС и ГО Республики Таджикистан
                        </div>
                        <div
                            style={{
                                fontSize: 12,
                                color: 'var(--color-neutral-600)',
                            }}
                        >
                            Система управления официальным сайтом
                        </div>
                    </div>
                </div>

                {children}

                <div
                    style={{
                        display: 'flex',
                        alignItems: 'center',
                        justifyContent: 'space-between',
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
        color: active ? 'var(--color-accent-700)' : 'var(--color-neutral-600)',
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
