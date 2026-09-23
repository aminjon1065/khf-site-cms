import { Form } from '@inertiajs/react';
import { update } from '@/routes/password';
import { AuthCard } from '@/ui/AuthCard';
import { Button } from '@/ui/Button';
import { Field, Input } from '@/ui/Field';
import { PasswordInput } from '@/ui/PasswordInput';

type Props = {
    token: string;
    email: string;
    passwordRules: string;
};

export default function ResetPassword({ token, email, passwordRules }: Props) {
    return (
        <AuthCard
            title="Новый пароль"
            lead="Придумайте новый пароль для входа."
        >
            <Form
                {...update.form()}
                transform={(data) => ({ ...data, token, email })}
                resetOnSuccess={['password', 'password_confirmation']}
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
                            label="Эл. почта"
                            error={errors.email}
                            htmlFor="email"
                        >
                            <Input
                                id="email"
                                name="email"
                                type="email"
                                autoComplete="username"
                                value={email}
                                readOnly
                                hasError={!!errors.email}
                            />
                        </Field>

                        <Field
                            label="Новый пароль"
                            required
                            error={errors.password}
                            hint={passwordRules}
                            htmlFor="password"
                        >
                            <PasswordInput
                                id="password"
                                name="password"
                                autoComplete="new-password"
                                autoFocus
                                required
                                hasError={!!errors.password}
                            />
                        </Field>

                        <Field
                            label="Повторите пароль"
                            required
                            error={errors.password_confirmation}
                            htmlFor="password_confirmation"
                        >
                            <PasswordInput
                                id="password_confirmation"
                                name="password_confirmation"
                                autoComplete="new-password"
                                required
                                hasError={!!errors.password_confirmation}
                            />
                        </Field>

                        <Button
                            type="submit"
                            variant="primary"
                            block
                            size="lg"
                            loading={processing}
                        >
                            Сохранить пароль
                        </Button>
                    </div>
                )}
            </Form>
        </AuthCard>
    );
}
