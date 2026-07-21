import { Head, useForm } from '@inertiajs/react';
import { KeyRound, Save, ShieldCheck } from 'lucide-react';
import { update as updateProfile } from '@/actions/App/Http/Controllers/Settings/ProfileController';
import { edit as editSecurity } from '@/actions/App/Http/Controllers/Settings/SecurityController';
import { useAuth } from '@/lib/auth';
import { Blueprint } from '@/ui/Blueprint';
import { Button, LinkButton } from '@/ui/Button';
import { Field, Input } from '@/ui/Field';
import { PageHeader } from '@/ui/PageHeader';

interface Props {
    mustVerifyEmail: boolean;
    status?: string | null;
}

export default function Profile({ mustVerifyEmail, status }: Props) {
    const user = useAuth();
    const form = useForm(updateProfile(), {
        name: user?.name ?? '',
        email: user?.email ?? '',
    });

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        form.submit({ preserveScroll: true });
    };

    return (
        <>
            <Head title="Профиль пользователя" />

            <PageHeader
                eyebrow="Учётная запись"
                title="Профиль пользователя"
                subtitle="Личные данные сотрудника и параметры безопасности входа."
                actions={
                    <LinkButton
                        href={editSecurity.url()}
                        variant="secondary"
                        icon={<ShieldCheck size={16} strokeWidth={1.75} />}
                    >
                        Безопасность
                    </LinkButton>
                }
            />

            <div className="cms-two-col grid grid-cols-1 items-start gap-4 lg:grid-cols-2">
                <Blueprint style={{ padding: 20 }}>
                    <form onSubmit={submit} className="flex flex-col gap-4">
                        <div>
                            <h2 className="ui-card-title">Личные данные</h2>
                            <p className="ui-hint">
                                Роль, подразделение и регион изменяются
                                администратором в разделе пользователей.
                            </p>
                        </div>

                        <Field
                            label="ФИО"
                            required
                            error={form.errors.name}
                            htmlFor="profile-name"
                        >
                            <Input
                                id="profile-name"
                                value={form.data.name}
                                onChange={(event) =>
                                    form.setData('name', event.target.value)
                                }
                                autoComplete="name"
                                hasError={Boolean(form.errors.name)}
                            />
                        </Field>

                        <Field
                            label="E-mail"
                            required
                            error={form.errors.email}
                            htmlFor="profile-email"
                        >
                            <Input
                                id="profile-email"
                                type="email"
                                value={form.data.email}
                                onChange={(event) =>
                                    form.setData('email', event.target.value)
                                }
                                autoComplete="email"
                                hasError={Boolean(form.errors.email)}
                            />
                        </Field>

                        {mustVerifyEmail &&
                            status === 'verification-link-sent' && (
                                <p className="ui-hint">
                                    Новая ссылка подтверждения отправлена на
                                    указанный адрес.
                                </p>
                            )}

                        <Button
                            type="submit"
                            variant="primary"
                            loading={form.processing}
                            disabled={!form.isDirty}
                            icon={<Save size={16} strokeWidth={1.75} />}
                        >
                            Сохранить профиль
                        </Button>
                    </form>
                </Blueprint>

                <Blueprint style={{ padding: 20 }}>
                    <div className="flex flex-col gap-4">
                        <div className="flex items-start gap-3">
                            <KeyRound
                                size={22}
                                strokeWidth={1.6}
                                className="mt-0.5 text-[var(--color-accent-700)]"
                            />
                            <div>
                                <h2 className="ui-card-title">
                                    Защита аккаунта
                                </h2>
                                <p className="ui-hint">
                                    Смените пароль, подключите
                                    приложение-аутентификатор и сохраните
                                    резервные коды.
                                </p>
                            </div>
                        </div>

                        <LinkButton
                            href={editSecurity.url()}
                            variant="secondary"
                        >
                            Открыть настройки безопасности
                        </LinkButton>
                    </div>
                </Blueprint>
            </div>
        </>
    );
}
