import { Link } from '@inertiajs/react';
import type { ButtonHTMLAttributes, CSSProperties, ReactNode } from 'react';
import { cn } from '@/lib/utils';

type Variant = 'primary' | 'secondary' | 'ghost' | 'danger' | 'danger-outline';
type Size = 'sm' | 'md' | 'lg';

const variantClass: Record<Variant, string> = {
    primary: 'ui-btn-primary',
    secondary: 'ui-btn-secondary',
    ghost: 'ui-btn-ghost',
    danger: 'ui-btn-danger',
    'danger-outline': 'ui-btn-danger-outline',
};

const sizeClass: Record<Size, string> = {
    sm: 'ui-btn-sm',
    md: '',
    lg: 'ui-btn-lg',
};

interface CommonProps {
    variant?: Variant;
    size?: Size;
    loading?: boolean;
    block?: boolean;
    icon?: ReactNode;
    iconRight?: ReactNode;
    className?: string;
    style?: CSSProperties;
    children?: ReactNode;
}

type ButtonProps = CommonProps & ButtonHTMLAttributes<HTMLButtonElement>;

export function Button({
    variant = 'secondary',
    size = 'md',
    loading = false,
    block = false,
    icon,
    iconRight,
    className,
    children,
    disabled,
    type = 'button',
    ...props
}: ButtonProps) {
    return (
        <button
            type={type}
            disabled={disabled || loading}
            className={cn(
                'ui-btn',
                variantClass[variant],
                sizeClass[size],
                block && 'ui-btn-block',
                loading && 'is-loading',
                className,
            )}
            {...props}
        >
            {icon}
            {children}
            {iconRight}
        </button>
    );
}

interface LinkButtonProps extends CommonProps {
    href: string;
    method?: 'get' | 'post' | 'put' | 'patch' | 'delete';
    as?: 'a' | 'button';
    preserveScroll?: boolean;
}

export function LinkButton({
    variant = 'secondary',
    size = 'md',
    block = false,
    icon,
    iconRight,
    className,
    children,
    href,
    ...props
}: LinkButtonProps) {
    return (
        <Link
            href={href}
            className={cn(
                'ui-btn',
                variantClass[variant],
                sizeClass[size],
                block && 'ui-btn-block',
                className,
            )}
            {...props}
        >
            {icon}
            {children}
            {iconRight}
        </Link>
    );
}

interface IconButtonProps extends ButtonHTMLAttributes<HTMLButtonElement> {
    variant?: Variant;
    label: string;
    children: ReactNode;
}

export function IconButton({
    variant = 'ghost',
    label,
    className,
    children,
    type = 'button',
    ...props
}: IconButtonProps) {
    return (
        <button
            type={type}
            aria-label={label}
            title={label}
            className={cn(
                'ui-btn ui-btn-icon',
                variantClass[variant],
                className,
            )}
            {...props}
        >
            {children}
        </button>
    );
}
