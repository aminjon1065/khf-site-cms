import type { LucideIcon } from 'lucide-react';
import { Monitor, Moon, Sun } from 'lucide-react';
import type { HTMLAttributes } from 'react';
import type { Appearance } from '@/hooks/use-appearance';
import { useAppearance } from '@/hooks/use-appearance';
import { cn } from '@/lib/utils';

const TABS: { value: Appearance; icon: LucideIcon; label: string }[] = [
    { value: 'light', icon: Sun, label: 'Светлая' },
    { value: 'dark', icon: Moon, label: 'Тёмная' },
    { value: 'system', icon: Monitor, label: 'Как в системе' },
];

export default function AppearanceToggleTab({
    className = '',
    ...props
}: HTMLAttributes<HTMLDivElement>) {
    const { appearance, updateAppearance } = useAppearance();

    return (
        <div
            className={cn('ui-seg', className)}
            role="radiogroup"
            aria-label="Тема оформления"
            {...props}
        >
            {TABS.map(({ value, icon: Icon, label }) => (
                <button
                    key={value}
                    type="button"
                    role="radio"
                    aria-checked={appearance === value}
                    className={cn(
                        'ui-seg-opt',
                        appearance === value && 'is-active',
                    )}
                    onClick={() => updateAppearance(value)}
                >
                    <Icon size={15} strokeWidth={1.75} />
                    {label}
                </button>
            ))}
        </div>
    );
}
