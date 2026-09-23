import { Form, Link } from '@inertiajs/react';
import { login } from '@/routes';
import { email } from '@/routes/password';
import { AuthCard } from '@/ui/AuthCard';
import { Button } from '@/ui/Button';
import { Field, Input } from '@/ui/Field';

export default function ForgotPassword({ status }: { status?: string }) {
    return (
        <AuthCard
            title="Восстановление доступа"
            lead="Укажите служебную почту — пришлём ссылку, по которой можно задать новый пароль."
            status={status}
        >
            <Form {...email.form()} disableWhileProcessing>
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

                        <Button
                            type="submit"
                            variant="primary"
                            block
                            size="lg"
                            loading={processing}
                        >
                            Отправить ссылку
                        </Button>

                        <Link href={login()} style={{ fontSize: 13 }}>
                            Вернуться ко входу
                        </Link>
                    </div>
                )}
            </Form>
        </AuthCard>
    );
}
