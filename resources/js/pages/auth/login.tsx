import { Form, Link } from '@inertiajs/react';
import { useT } from '@/lib/i18n';
import { store } from '@/routes/login';
import { request } from '@/routes/password';
import { AuthCard } from '@/ui/AuthCard';
import { Button } from '@/ui/Button';
import { Checkbox, Field, Input } from '@/ui/Field';
import { PasswordInput } from '@/ui/PasswordInput';

export default function Login({
    canResetPassword = true,
    status,
}: {
    canResetPassword?: boolean;
    status?: string;
}) {
    const { t } = useT();

    return (
        <AuthCard
            title={t('auth.login_title')}
            lead="Доступ только для уполномоченных сотрудников Комитета"
            status={status}
        >
            <Form
                {...store.form()}
                resetOnSuccess={['password']}
                disableWhileProcessing
            >
                {({ processing, errors }) => (
                    <div
                        style={{
                            display: 'flex',
                            flexDirection: 'column',
                            gap: 14,
                        }}
                    >
                        <Field
                            label="Служебная эл. почта"
                            required
                            error={errors.email}
                            htmlFor="email"
                        >
                            <Input
                                id="email"
                                name="email"
                                type="email"
                                autoComplete="username"
                                placeholder="f.nazarov@khf.tj"
                                autoFocus
                                required
                                hasError={!!errors.email}
                            />
                        </Field>

                        <Field
                            label={t('auth.password')}
                            required
                            error={errors.password}
                            htmlFor="password"
                        >
                            <PasswordInput
                                id="password"
                                name="password"
                                autoComplete="current-password"
                                placeholder="••••••••••"
                                required
                                hasError={!!errors.password}
                            />
                        </Field>

                        <div
                            style={{
                                display: 'flex',
                                alignItems: 'center',
                                justifyContent: 'space-between',
                            }}
                        >
                            <Checkbox
                                name="remember"
                                label={t('auth.remember')}
                                value="1"
                            />
                            {canResetPassword && (
                                <Link
                                    href={request.url()}
                                    style={{ fontSize: 13 }}
                                >
                                    Восстановить доступ
                                </Link>
                            )}
                        </div>

                        <Button
                            type="submit"
                            variant="primary"
                            block
                            size="lg"
                            loading={processing}
                        >
                            {t('auth.sign_in')}
                        </Button>
                    </div>
                )}
            </Form>

            <p
                style={{
                    fontSize: 12,
                    color: 'var(--color-neutral-500)',
                    marginTop: 16,
                    marginBottom: 0,
                }}
            >
                {t('auth.security_notice')}
            </p>
        </AuthCard>
    );
}
