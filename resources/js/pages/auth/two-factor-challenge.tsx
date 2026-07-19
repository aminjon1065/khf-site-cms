import { Head, router, useForm } from '@inertiajs/react';
import { useRef, useState } from 'react';
import { useT } from '@/lib/i18n';
import { Blueprint } from '@/ui/Blueprint';
import { Button } from '@/ui/Button';
import { Field, Input, InputError } from '@/ui/Field';

export default function TwoFactorChallenge() {
    const { t } = useT();
    const [recovery, setRecovery] = useState(false);
    const [digits, setDigits] = useState<string[]>(['', '', '', '', '', '']);
    const inputs = useRef<Array<HTMLInputElement | null>>([]);

    const form = useForm<{ code: string; recovery_code: string }>({
        code: '',
        recovery_code: '',
    });

    const setDigit = (i: number, value: string) => {
        const v = value.replace(/\D/g, '').slice(-1);
        const next = [...digits];
        next[i] = v;
        setDigits(next);

        if (v && i < 5) {
            inputs.current[i + 1]?.focus();
        }
    };

    const onKeyDown = (i: number, e: React.KeyboardEvent<HTMLInputElement>) => {
        if (e.key === 'Backspace' && !digits[i] && i > 0) {
            inputs.current[i - 1]?.focus();
        }
    };

    const onPaste = (e: React.ClipboardEvent) => {
        const text = e.clipboardData
            .getData('text')
            .replace(/\D/g, '')
            .slice(0, 6);

        if (text) {
            e.preventDefault();
            const next = text.split('');
            setDigits([...next, ...Array(6 - next.length).fill('')]);
            inputs.current[Math.min(next.length, 5)]?.focus();
        }
    };

    const submit = (e: React.FormEvent) => {
        e.preventDefault();

        if (recovery) {
            router.post(
                '/two-factor-challenge',
                { recovery_code: form.data.recovery_code },
                { onError: (errs) => form.setError(errs as never) },
            );
        } else {
            router.post(
                '/two-factor-challenge',
                { code: digits.join('') },
                { onError: (errs) => form.setError(errs as never) },
            );
        }
    };

    return (
        <Blueprint style={{ background: '#fff', padding: 28 }}>
            <Head title={t('auth.2fa_title')} />
            <h2
                style={{
                    fontSize: 21,
                    fontWeight: 600,
                    fontFamily: 'var(--font-heading)',
                }}
            >
                Подтверждение входа
            </h2>
            <p
                style={{
                    fontSize: 13,
                    color: 'var(--color-neutral-600)',
                    marginTop: 4,
                    marginBottom: 18,
                }}
            >
                {recovery ? 'Введите код восстановления' : t('auth.2fa_sub')}
            </p>

            <form
                onSubmit={submit}
                style={{ display: 'flex', flexDirection: 'column', gap: 16 }}
            >
                {recovery ? (
                    <Field
                        label="Код восстановления"
                        error={form.errors.recovery_code}
                    >
                        <Input
                            value={form.data.recovery_code}
                            onChange={(e) =>
                                form.setData('recovery_code', e.target.value)
                            }
                            autoFocus
                            autoComplete="one-time-code"
                            placeholder="xxxxxxxx-xxxxxxxx"
                        />
                    </Field>
                ) : (
                    <div>
                        <div className="ui-otp" onPaste={onPaste}>
                            {digits.map((d, i) => (
                                <input
                                    key={i}
                                    ref={(el) => {
                                        inputs.current[i] = el;
                                    }}
                                    inputMode="numeric"
                                    autoComplete={
                                        i === 0 ? 'one-time-code' : 'off'
                                    }
                                    maxLength={1}
                                    value={d}
                                    autoFocus={i === 0}
                                    onChange={(e) =>
                                        setDigit(i, e.target.value)
                                    }
                                    onKeyDown={(e) => onKeyDown(i, e)}
                                    aria-label={`Цифра ${i + 1}`}
                                />
                            ))}
                        </div>
                        <InputError message={form.errors.code} />
                    </div>
                )}

                <Button
                    type="submit"
                    variant="primary"
                    block
                    size="lg"
                    loading={form.processing}
                >
                    Подтвердить и войти
                </Button>

                <button
                    type="button"
                    onClick={() => setRecovery((r) => !r)}
                    style={{
                        border: 0,
                        background: 'transparent',
                        color: 'var(--color-accent-700)',
                        fontSize: 13,
                        cursor: 'pointer',
                    }}
                >
                    {recovery
                        ? 'Ввести код из приложения'
                        : t('auth.2fa_recovery')}
                </button>
            </form>
        </Blueprint>
    );
}
