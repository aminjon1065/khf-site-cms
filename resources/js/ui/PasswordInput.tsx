import { Eye, EyeOff } from 'lucide-react';
import { useState } from 'react';
import type { ComponentProps } from 'react';
import { useT } from '@/lib/i18n';
import { Input } from '@/ui/Field';

/** Поле пароля с кнопкой «Показать пароль». */
export function PasswordInput(
    props: Omit<ComponentProps<typeof Input>, 'type' | 'trailing'>,
) {
    const { t } = useT();
    const [shown, setShown] = useState(false);

    return (
        <Input
            {...props}
            type={shown ? 'text' : 'password'}
            trailing={
                <button
                    type="button"
                    onClick={() => setShown((value) => !value)}
                    aria-label={
                        shown
                            ? t('auth.hide_password')
                            : t('auth.show_password')
                    }
                    style={{
                        border: 0,
                        background: 'transparent',
                        cursor: 'pointer',
                        color: 'var(--color-neutral-600)',
                        padding: 4,
                        display: 'flex',
                    }}
                >
                    {shown ? (
                        <EyeOff size={16} strokeWidth={1.5} />
                    ) : (
                        <Eye size={16} strokeWidth={1.5} />
                    )}
                </button>
            }
        />
    );
}
