import { Head, useForm } from '@inertiajs/react';
import { KeyRound, RefreshCw, ShieldCheck, ShieldOff } from 'lucide-react';
import { edit as editProfile } from '@/actions/App/Http/Controllers/Settings/ProfileController';
import { update as updatePassword } from '@/actions/App/Http/Controllers/Settings/SecurityController';
import { store as confirmTwoFactor } from '@/actions/Laravel/Fortify/Http/Controllers/ConfirmedTwoFactorAuthenticationController';
import { store as regenerateRecoveryCodes } from '@/actions/Laravel/Fortify/Http/Controllers/RecoveryCodeController';
import {
    destroy as disableTwoFactor,
    store as enableTwoFactor,
} from '@/actions/Laravel/Fortify/Http/Controllers/TwoFactorAuthenticationController';
import { Blueprint } from '@/ui/Blueprint';
import { Button, LinkButton } from '@/ui/Button';
import { Field, Input } from '@/ui/Field';
import { PageHeader } from '@/ui/PageHeader';

interface TwoFactorState {
    enabled: boolean;
    pending: boolean;
    qr_code_svg: string | null;
    recovery_codes: string[];
}

interface Props {
    passwordRules: string;
    twoFactor: TwoFactorState;
}

export default function Security({ passwordRules, twoFactor }: Props) {
    const passwordForm = useForm(updatePassword(), {
        current_password: '',
        password: '',
        password_confirmation: '',
    });
    const enableForm = useForm(enableTwoFactor(), {});
    const confirmForm = useForm(confirmTwoFactor(), { code: '' });
    const disableForm = useForm(disableTwoFactor(), {});
    const recoveryForm = useForm(regenerateRecoveryCodes(), {});

    const updatePasswordSubmit = (event: React.FormEvent) => {
        event.preventDefault();
        passwordForm.submit({
            preserveScroll: true,
            onSuccess: () => passwordForm.reset(),
        });
    };

    return (
        <>
            <Head title="Безопасность аккаунта" />

            <PageHeader
                eyebrow="Профиль / Безопасность"
                title="Безопасность аккаунта"
                subtitle="Пароль, двухфакторная аутентификация и резервные коды."
                actions={
                    <LinkButton href={editProfile.url()} variant="ghost">
                        Вернуться в профиль
                    </LinkButton>
                }
            />

            <div className="grid grid-cols-1 items-start gap-4 xl:grid-cols-2">
                <Blueprint style={{ padding: 20 }}>
                    <form
                        onSubmit={updatePasswordSubmit}
                        className="flex flex-col gap-4"
                    >
                        <div className="flex items-start gap-3">
                            <KeyRound
                                size={22}
                                strokeWidth={1.6}
                                className="mt-0.5 text-[var(--color-accent-700)]"
                            />
                            <div>
                                <h2 className="ui-card-title">Смена пароля</h2>
                                <p className="ui-hint">{passwordRules}</p>
                            </div>
                        </div>

                        <Field
                            label="Текущий пароль"
                            required
                            error={passwordForm.errors.current_password}
                        >
                            <Input
                                type="password"
                                value={passwordForm.data.current_password}
                                onChange={(event) =>
                                    passwordForm.setData(
                                        'current_password',
                                        event.target.value,
                                    )
                                }
                                autoComplete="current-password"
                            />
                        </Field>

                        <Field
                            label="Новый пароль"
                            required
                            error={passwordForm.errors.password}
                        >
                            <Input
                                type="password"
                                value={passwordForm.data.password}
                                onChange={(event) =>
                                    passwordForm.setData(
                                        'password',
                                        event.target.value,
                                    )
                                }
                                autoComplete="new-password"
                            />
                        </Field>

                        <Field label="Повторите новый пароль" required>
                            <Input
                                type="password"
                                value={passwordForm.data.password_confirmation}
                                onChange={(event) =>
                                    passwordForm.setData(
                                        'password_confirmation',
                                        event.target.value,
                                    )
                                }
                                autoComplete="new-password"
                            />
                        </Field>

                        <Button
                            type="submit"
                            variant="primary"
                            loading={passwordForm.processing}
                        >
                            Обновить пароль
                        </Button>
                    </form>
                </Blueprint>

                <Blueprint style={{ padding: 20 }}>
                    <div className="flex flex-col gap-4">
                        <div className="flex items-start gap-3">
                            <ShieldCheck
                                size={22}
                                strokeWidth={1.6}
                                className="mt-0.5 text-[var(--color-accent-700)]"
                            />
                            <div>
                                <h2 className="ui-card-title">
                                    Двухфакторная аутентификация
                                </h2>
                                <p className="ui-hint">
                                    Код из приложения-аутентификатора
                                    потребуется при каждом новом входе.
                                </p>
                            </div>
                        </div>

                        {!twoFactor.enabled && !twoFactor.pending && (
                            <Button
                                variant="primary"
                                loading={enableForm.processing}
                                onClick={() =>
                                    enableForm.submit({ preserveScroll: true })
                                }
                            >
                                Включить 2FA
                            </Button>
                        )}

                        {twoFactor.pending && (
                            <div className="flex flex-col gap-4">
                                <p className="text-sm text-[var(--color-neutral-700)]">
                                    Отсканируйте QR-код, затем введите
                                    шестизначный код для подтверждения.
                                </p>

                                {twoFactor.qr_code_svg && (
                                    <div
                                        className="w-fit rounded-md border border-[var(--color-neutral-200)] bg-white p-3"
                                        dangerouslySetInnerHTML={{
                                            __html: twoFactor.qr_code_svg,
                                        }}
                                    />
                                )}

                                <Field
                                    label="Код подтверждения"
                                    required
                                    error={confirmForm.errors.code}
                                >
                                    <Input
                                        inputMode="numeric"
                                        maxLength={6}
                                        value={confirmForm.data.code}
                                        onChange={(event) =>
                                            confirmForm.setData(
                                                'code',
                                                event.target.value.replace(
                                                    /\D/g,
                                                    '',
                                                ),
                                            )
                                        }
                                        autoComplete="one-time-code"
                                    />
                                </Field>

                                <div className="flex flex-wrap gap-2">
                                    <Button
                                        variant="primary"
                                        loading={confirmForm.processing}
                                        onClick={() =>
                                            confirmForm.submit({
                                                preserveScroll: true,
                                            })
                                        }
                                    >
                                        Подтвердить 2FA
                                    </Button>
                                    <Button
                                        variant="danger-outline"
                                        loading={disableForm.processing}
                                        onClick={() =>
                                            disableForm.submit({
                                                preserveScroll: true,
                                            })
                                        }
                                    >
                                        Отменить настройку
                                    </Button>
                                </div>
                            </div>
                        )}

                        {twoFactor.enabled && (
                            <div className="flex flex-col gap-4">
                                <div className="rounded-md border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-800">
                                    2FA включена и подтверждена.
                                </div>

                                <div>
                                    <h3 className="mb-2 text-sm font-semibold">
                                        Резервные коды
                                    </h3>
                                    <div className="grid grid-cols-1 gap-2 rounded-md bg-[var(--color-neutral-50)] p-3 font-mono text-sm sm:grid-cols-2">
                                        {twoFactor.recovery_codes.map(
                                            (code) => (
                                                <code key={code}>{code}</code>
                                            ),
                                        )}
                                    </div>
                                    <p className="ui-hint mt-2">
                                        Храните эти коды отдельно от устройства
                                        с приложением-аутентификатором.
                                    </p>
                                </div>

                                <div className="flex flex-wrap gap-2">
                                    <Button
                                        variant="secondary"
                                        icon={<RefreshCw size={15} />}
                                        loading={recoveryForm.processing}
                                        onClick={() =>
                                            recoveryForm.submit({
                                                preserveScroll: true,
                                            })
                                        }
                                    >
                                        Обновить резервные коды
                                    </Button>
                                    <Button
                                        variant="danger-outline"
                                        icon={<ShieldOff size={15} />}
                                        loading={disableForm.processing}
                                        onClick={() =>
                                            disableForm.submit({
                                                preserveScroll: true,
                                            })
                                        }
                                    >
                                        Отключить 2FA
                                    </Button>
                                </div>
                            </div>
                        )}
                    </div>
                </Blueprint>
            </div>
        </>
    );
}
