import { Form } from '@inertiajs/react';
import { store } from '@/routes/password/confirm';
import { AuthCard } from '@/ui/AuthCard';
import { Button } from '@/ui/Button';
import { Field } from '@/ui/Field';
import { PasswordInput } from '@/ui/PasswordInput';

export default function ConfirmPassword() {
    return (
        <AuthCard
            title="Подтвердите пароль"
            lead="Это действие меняет настройки безопасности — введите пароль ещё раз."
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
                            label="Пароль"
                            required
                            error={errors.password}
                            htmlFor="password"
                        >
                            <PasswordInput
                                id="password"
                                name="password"
                                autoComplete="current-password"
                                autoFocus
                                required
                                hasError={!!errors.password}
                            />
                        </Field>

                        <Button
                            type="submit"
                            variant="primary"
                            block
                            size="lg"
                            loading={processing}
                        >
                            Подтвердить
                        </Button>
                    </div>
                )}
            </Form>
        </AuthCard>
    );
}
