import { Check } from 'lucide-react';
import type { ReactNode } from 'react';
import { localeShort } from '@/lib/domain';
import type { ContentLocale } from '@/lib/domain';
import { cn } from '@/lib/utils';

export interface Step {
    label: string;
}

export function Stepper({
    steps,
    current,
    onStep,
}: {
    steps: Step[];
    current: number;
    onStep?: (index: number) => void;
}) {
    return (
        <div className="ui-stepper">
            {steps.map((s, i) => {
                const state =
                    i < current ? 'is-done' : i === current ? 'is-active' : '';

                return (
                    <div
                        key={i}
                        className={cn('ui-step', state)}
                        style={{ flex: i === steps.length - 1 ? 'none' : 1 }}
                    >
                        <button
                            type="button"
                            onClick={onStep ? () => onStep(i) : undefined}
                            style={{
                                display: 'flex',
                                alignItems: 'center',
                                gap: 9,
                                background: 'transparent',
                                border: 0,
                                cursor: onStep ? 'pointer' : 'default',
                                padding: 0,
                            }}
                        >
                            <span className="num">
                                {i < current ? (
                                    <Check size={14} strokeWidth={2} />
                                ) : (
                                    i + 1
                                )}
                            </span>
                            <span className="step-label">{s.label}</span>
                        </button>
                        {i < steps.length - 1 && <span className="connector" />}
                    </div>
                );
            })}
        </div>
    );
}

export interface Tab {
    key: string;
    label: ReactNode;
}

export function Tabs({
    tabs,
    active,
    onChange,
}: {
    tabs: Tab[];
    active: string;
    onChange: (key: string) => void;
}) {
    return (
        <div className="ui-tabs" role="tablist">
            {tabs.map((t) => (
                <button
                    key={t.key}
                    type="button"
                    role="tab"
                    aria-selected={active === t.key}
                    className={cn('ui-tab', active === t.key && 'is-active')}
                    onClick={() => onChange(t.key)}
                >
                    {t.label}
                </button>
            ))}
        </div>
    );
}

/** Language tabs with per-locale completeness percentage. */
export function LanguageTabs({
    active,
    onChange,
    completeness,
    locales = ['tg', 'ru', 'en'],
}: {
    active: ContentLocale;
    onChange: (locale: ContentLocale) => void;
    completeness: Partial<Record<ContentLocale, number>>;
    locales?: ContentLocale[];
}) {
    return (
        <div style={{ display: 'flex' }} role="tablist">
            {locales.map((l) => {
                const pct = completeness[l] ?? 0;

                return (
                    <button
                        key={l}
                        type="button"
                        role="tab"
                        aria-selected={active === l}
                        className={cn(
                            'ui-langtab',
                            active === l && 'is-active',
                        )}
                        onClick={() => onChange(l)}
                    >
                        {localeShort[l]}
                        <span
                            className={cn(
                                'pct',
                                pct >= 100 && 'full',
                                pct === 0 && 'empty',
                            )}
                        >
                            {pct}%
                        </span>
                    </button>
                );
            })}
        </div>
    );
}
