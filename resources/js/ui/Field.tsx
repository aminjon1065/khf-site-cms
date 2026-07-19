import { TriangleAlert } from 'lucide-react';
import type {
    InputHTMLAttributes,
    ReactNode,
    SelectHTMLAttributes,
    TextareaHTMLAttributes,
} from 'react';
import { cn } from '@/lib/utils';

export function Field({
    label,
    required,
    hint,
    error,
    htmlFor,
    className,
    children,
}: {
    label?: ReactNode;
    required?: boolean;
    hint?: ReactNode;
    error?: string | null;
    htmlFor?: string;
    className?: string;
    children: ReactNode;
}) {
    return (
        <div className={cn('ui-field', className)}>
            {label && (
                <label className="ui-label" htmlFor={htmlFor}>
                    {label}
                    {required && <span className="req">*</span>}
                </label>
            )}
            {children}
            {error ? (
                <InputError message={error} />
            ) : hint ? (
                <span className="ui-hint">{hint}</span>
            ) : null}
        </div>
    );
}

export function InputError({ message }: { message?: string | null }) {
    if (!message) {
        return null;
    }

    return (
        <span className="ui-input-error">
            <TriangleAlert size={13} strokeWidth={1.5} />
            {message}
        </span>
    );
}

interface InputProps extends InputHTMLAttributes<HTMLInputElement> {
    hasError?: boolean;
    leadingIcon?: ReactNode;
    trailing?: ReactNode;
}

export function Input({
    hasError,
    leadingIcon,
    trailing,
    className,
    ...props
}: InputProps) {
    const input = (
        <input
            className={cn('ui-input', hasError && 'has-error', className)}
            {...props}
        />
    );

    if (!leadingIcon && !trailing) {
        return input;
    }

    return (
        <span className="ui-input-wrap">
            {leadingIcon && (
                <span className="ui-input-icon">{leadingIcon}</span>
            )}
            {input}
            {trailing && <span className="ui-input-trailing">{trailing}</span>}
        </span>
    );
}

export function Textarea({
    hasError,
    className,
    ...props
}: TextareaHTMLAttributes<HTMLTextAreaElement> & { hasError?: boolean }) {
    return (
        <textarea
            className={cn('ui-textarea', hasError && 'has-error', className)}
            {...props}
        />
    );
}

interface SelectProps extends SelectHTMLAttributes<HTMLSelectElement> {
    hasError?: boolean;
    options?: { value: string | number; label: string }[];
    placeholder?: string;
}

export function Select({
    hasError,
    options,
    placeholder,
    className,
    children,
    ...props
}: SelectProps) {
    return (
        <select
            className={cn('ui-select', hasError && 'has-error', className)}
            {...props}
        >
            {placeholder && <option value="">{placeholder}</option>}
            {options
                ? options.map((o) => (
                      <option key={o.value} value={o.value}>
                          {o.label}
                      </option>
                  ))
                : children}
        </select>
    );
}

export function Checkbox({
    label,
    className,
    ...props
}: InputHTMLAttributes<HTMLInputElement> & { label?: ReactNode }) {
    return (
        <label className={cn('ui-check', className)}>
            <input type="checkbox" {...props} />
            <span className="box">
                <svg
                    width="11"
                    height="11"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    strokeWidth="3"
                >
                    <path d="M20 6 9 17l-5-5" />
                </svg>
            </span>
            {label && <span>{label}</span>}
        </label>
    );
}

export function Radio({
    label,
    className,
    ...props
}: InputHTMLAttributes<HTMLInputElement> & { label?: ReactNode }) {
    return (
        <label className={cn('ui-radio', className)}>
            <input type="radio" {...props} />
            <span className="dot" />
            {label && <span>{label}</span>}
        </label>
    );
}

/** A selectable card (used for hazard type / severity choosers). */
export function RadioCard({
    active,
    onSelect,
    className,
    children,
}: {
    active: boolean;
    onSelect: () => void;
    className?: string;
    children: ReactNode;
}) {
    return (
        <button
            type="button"
            aria-pressed={active}
            onClick={onSelect}
            className={cn('ui-radiocard', active && 'is-active', className)}
        >
            {children}
        </button>
    );
}

export interface SegmentedOption<T extends string> {
    value: T;
    label: ReactNode;
    icon?: ReactNode;
}

export function Segmented<T extends string>({
    value,
    onChange,
    options,
    className,
}: {
    value: T;
    onChange: (value: T) => void;
    options: SegmentedOption<T>[];
    className?: string;
}) {
    return (
        <div className={cn('ui-seg', className)} role="tablist">
            {options.map((o) => (
                <button
                    key={o.value}
                    type="button"
                    role="tab"
                    aria-selected={value === o.value}
                    className={cn(
                        'ui-seg-opt',
                        value === o.value && 'is-active',
                    )}
                    onClick={() => onChange(o.value)}
                >
                    {o.icon}
                    {o.label}
                </button>
            ))}
        </div>
    );
}

/** Native date / datetime input styled as an `.ui-input`. */
export function DatePicker({
    withTime = false,
    hasError,
    className,
    ...props
}: InputHTMLAttributes<HTMLInputElement> & {
    withTime?: boolean;
    hasError?: boolean;
}) {
    return (
        <input
            type={withTime ? 'datetime-local' : 'date'}
            className={cn('ui-input', hasError && 'has-error', className)}
            {...props}
        />
    );
}
