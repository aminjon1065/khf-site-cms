import { Form, Head, Link } from '@inertiajs/react';
import { Eye, EyeOff } from 'lucide-react';
import { useState } from 'react';
import { useT } from '@/lib/i18n';
import { Blueprint } from '@/ui/Blueprint';
import { Button } from '@/ui/Button';
import { Checkbox, Field, Input } from '@/ui/Field';

export default function Login({
    canResetPassword = true,
}: {
    canResetPassword?: boolean;
    status?: string;
}) {
    const { t } = useT();
    const [show, setShow] = useState(false);

    return (
        <Blueprint style={{ background: '#fff', padding: 28 }}>
            <Head title={t('auth.login_title')} />
            <h2
                style={{
                    fontSize: 21,
                    fontWeight: 600,
                    fontFamily: 'var(--font-heading)',
                }}
            >
                {t('auth.login_title')}
            </h2>
            <p
                style={{
                    fontSize: 13,
                    color: 'var(--color-neutral-600)',
                    marginTop: 4,
                    marginBottom: 18,
                }}
            >
                Доступ только для уполномоченных сотрудников Комитета
            </p>

            <Form
                action="/login"
                method="post"
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
                            label="Служебный логин или email"
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
                            <Input
                                id="password"
                                name="password"
                                type={show ? 'text' : 'password'}
                                autoComplete="current-password"
                                placeholder="••••••••••"
                                required
                                hasError={!!errors.password}
                                trailing={
                                    <button
                                        type="button"
                                        onClick={() => setShow((s) => !s)}
                                        aria-label={
                                            show
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
                                        {show ? (
                                            <EyeOff
                                                size={16}
                                                strokeWidth={1.5}
                                            />
                                        ) : (
                                            <Eye size={16} strokeWidth={1.5} />
                                        )}
                                    </button>
                                }
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
                                    href="/forgot-password"
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
        </Blueprint>
    );
}
