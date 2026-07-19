import { Search } from 'lucide-react';
import type { InputHTMLAttributes, ReactNode } from 'react';
import { cn } from '@/lib/utils';
import { Input } from './Field';

export function FilterBar({
    children,
    className,
}: {
    children: ReactNode;
    className?: string;
}) {
    return <div className={cn('ui-filterbar', className)}>{children}</div>;
}

export function SearchInput({
    width = 260,
    className,
    ...props
}: InputHTMLAttributes<HTMLInputElement> & { width?: number }) {
    return (
        <Input
            type="search"
            leadingIcon={<Search size={16} strokeWidth={1.5} />}
            style={{ width }}
            className={className}
            {...props}
        />
    );
}

export interface SavedView {
    key: string;
    label: string;
    count?: number;
}

export function SavedViews({
    views,
    active,
    onChange,
}: {
    views: SavedView[];
    active: string;
    onChange: (key: string) => void;
}) {
    return (
        <div className="ui-savedviews ui-scroll" role="tablist">
            {views.map((v) => (
                <button
                    key={v.key}
                    type="button"
                    role="tab"
                    aria-selected={active === v.key}
                    className={cn(
                        'ui-savedview',
                        active === v.key && 'is-active',
                    )}
                    onClick={() => onChange(v.key)}
                >
                    {v.label}
                    {v.count !== undefined && (
                        <span className="count">{v.count}</span>
                    )}
                </button>
            ))}
        </div>
    );
}
