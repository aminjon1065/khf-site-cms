import { Head, Link, router, useForm } from '@inertiajs/react';
import { ArrowLeft, Save, ShieldOff } from 'lucide-react';
import { useState } from 'react';
import UserController from '@/actions/App/Http/Controllers/Cms/UserController';
import { useCan } from '@/lib/auth';
import { Blueprint } from '@/ui/Blueprint';
import { Button, LinkButton } from '@/ui/Button';
import { Checkbox, Field, Input, Select } from '@/ui/Field';
import { ConfirmDialog } from '@/ui/Overlay';
import { PageHeader } from '@/ui/PageHeader';

interface Option {
    value: string | number;
    label: string;
    description?: string;
}

interface UserData {
    id: number;
    name: string;
    email: string;
    role: string | null;
    region_id: number | null;
    position: string | null;
    department: string | null;
    is_active: boolean;
    is_self: boolean;
    two_factor_enabled: boolean;
}

interface Props {
    user: UserData | null;
    reference: { roles: Option[]; regions: Option[] };
}

export default function UserForm({ user, reference }: Props) {
    const can = useCan();
    const isEdit = !!user;
    const isSelf = user?.is_self ?? false;
    const [resetOpen, setResetOpen] = useState(false);
    const [resetting, setResetting] = useState(false);

    const form = useForm({
        name: user?.name ?? '',
        email: user?.email ?? '',
        password: '',
        password_confirmation: '',
        // No default role: a new account must get its access level on purpose
        // (the first option used to be «Суперадминистратор»).
        role: user?.role ?? '',
        region_id: (user?.region_id ?? '') as number | '',
        position: user?.position ?? '',
        department: user?.department ?? '',
        is_active: user?.is_active ?? true,
    });
    const { data, setData, processing, errors } = form;

    const fieldError = (key: string): string | undefined =>
        (errors as Record<string, string | undefined>)[key];

    const submit = () => {
        form.transform((d) => ({
            ...d,
            region_id: d.region_id === '' ? null : d.region_id,
        }));

        if (isEdit && user) {
            form.put(UserController.update.url(user.id), {
                preserveScroll: true,
            });
        } else {
            form.post(UserController.store.url(), { preserveScroll: true });
        }
    };

    const roleDescription = reference.roles.find(
        (r) => r.value === data.role,
    )?.description;

    return (
        <>
            <Head
                title={isEdit ? 'Редактирование сотрудника' : 'Новый сотрудник'}
            />

            <PageHeader
                eyebrow={
                    <Link
                        href={UserController.index.url()}
                        style={{
                            display: 'inline-flex',
                            alignItems: 'center',
                            gap: 6,
                            color: 'var(--color-neutral-600)',
                            textDecoration: 'none',
                        }}
                    >
                        <ArrowLeft size={14} strokeWidth={1.75} /> Пользователи
                    </Link>
                }
                title={isEdit ? data.name || 'Сотрудник' : 'Новый сотрудник'}
                subtitle="Учётные данные, роль и доступ к системе управления."
                actions={
                    <Button
                        variant="primary"
                        icon={<Save size={16} strokeWidth={1.75} />}
                        loading={processing}
                        onClick={submit}
                    >
                        Сохранить
                    </Button>
                }
            />

            <div
                className="cms-two-col"
                style={{
                    display: 'grid',
                    gridTemplateColumns: '1fr 1fr',
                    gap: 16,
                    alignItems: 'start',
                }}
            >
                <Blueprint style={{ padding: 20 }}>
                    <h3 className="ui-card-title" style={{ marginTop: 0 }}>
                        Учётные данные
                    </h3>
                    <Field label="ФИО" required error={fieldError('name')}>
                        <Input
                            value={data.name}
                            onChange={(e) => setData('name', e.target.value)}
                            hasError={!!fieldError('name')}
                            placeholder="Фаридун Назаров"
                        />
                    </Field>
                    <Field
                        label="Эл. почта"
                        required
                        error={fieldError('email')}
                    >
                        <Input
                            type="email"
                            value={data.email}
                            onChange={(e) => setData('email', e.target.value)}
                            hasError={!!fieldError('email')}
                            placeholder="f.nazarov@khf.tj"
                            className="ui-mono"
                        />
                    </Field>
                    <Field
                        label={isEdit ? 'Новый пароль' : 'Пароль'}
                        required={!isEdit}
                        hint={
                            isEdit
                                ? 'Оставьте пустым, чтобы не менять.'
                                : undefined
                        }
                        error={fieldError('password')}
                    >
                        <Input
                            type="password"
                            value={data.password}
                            onChange={(e) =>
                                setData('password', e.target.value)
                            }
                            hasError={!!fieldError('password')}
                            autoComplete="new-password"
                        />
                    </Field>
                    <Field label="Повторите пароль">
                        <Input
                            type="password"
                            value={data.password_confirmation}
                            onChange={(e) =>
                                setData('password_confirmation', e.target.value)
                            }
                            autoComplete="new-password"
                        />
                    </Field>
                </Blueprint>

                <Blueprint style={{ padding: 20 }}>
                    <h3 className="ui-card-title" style={{ marginTop: 0 }}>
                        Роль и доступ
                    </h3>
                    <Field label="Роль" required error={fieldError('role')}>
                        <Select
                            value={data.role}
                            placeholder="Выберите роль"
                            options={reference.roles.map((r) => ({
                                value: String(r.value),
                                label: r.label,
                            }))}
                            onChange={(e) => setData('role', e.target.value)}
                            disabled={isSelf}
                        />
                    </Field>
                    {roleDescription && (
                        <p
                            style={{
                                margin: '-6px 0 12px',
                                fontSize: 12,
                                color: 'var(--color-neutral-500)',
                            }}
                        >
                            {roleDescription}
                        </p>
                    )}
                    <Field label="Регион" hint="Для региональных ролей.">
                        <Select
                            value={
                                data.region_id === ''
                                    ? ''
                                    : String(data.region_id)
                            }
                            options={[
                                { value: '', label: '— Весь Таджикистан —' },
                                ...reference.regions.map((r) => ({
                                    value: String(r.value),
                                    label: r.label,
                                })),
                            ]}
                            onChange={(e) =>
                                setData(
                                    'region_id',
                                    e.target.value === ''
                                        ? ''
                                        : Number(e.target.value),
                                )
                            }
                        />
                    </Field>
                    <div style={{ marginTop: 8 }}>
                        <Checkbox
                            label="Учётная запись активна"
                            checked={data.is_active}
                            disabled={isSelf}
                            onChange={(e) =>
                                setData('is_active', e.target.checked)
                            }
                        />
                        {isSelf && (
                            <p
                                style={{
                                    margin: '6px 0 0',
                                    fontSize: 12,
                                    color: 'var(--color-neutral-500)',
                                }}
                            >
                                Нельзя изменить собственную роль или отключить
                                свою учётную запись.
                            </p>
                        )}
                    </div>
                </Blueprint>
            </div>

            <div
                className="cms-two-col"
                style={{
                    display: 'grid',
                    gridTemplateColumns: '1fr 1fr',
                    gap: 16,
                    marginTop: 16,
                }}
            >
                <Blueprint style={{ padding: 20 }}>
                    <h3 className="ui-card-title" style={{ marginTop: 0 }}>
                        Должность
                    </h3>
                    <Field label="Должность">
                        <Input
                            value={data.position}
                            onChange={(e) =>
                                setData('position', e.target.value)
                            }
                            placeholder="Главный редактор"
                        />
                    </Field>
                    <Field label="Подразделение">
                        <Input
                            value={data.department}
                            onChange={(e) =>
                                setData('department', e.target.value)
                            }
                            placeholder="Пресс-служба"
                        />
                    </Field>
                </Blueprint>

                {isEdit && !isSelf && can('users.edit') && (
                    <Blueprint style={{ padding: 20 }}>
                        <h3 className="ui-card-title" style={{ marginTop: 0 }}>
                            Двухфакторная аутентификация
                        </h3>
                        <p
                            style={{
                                margin: '0 0 12px',
                                fontSize: 13,
                                color: 'var(--color-neutral-600)',
                            }}
                        >
                            {user!.two_factor_enabled
                                ? 'Включена. Если сотрудник потерял или сменил телефон, сбросьте её — при следующем входе он настроит её заново.'
                                : 'Не настроена.'}
                        </p>
                        {user!.two_factor_enabled && (
                            <Button
                                variant="secondary"
                                icon={
                                    <ShieldOff size={15} strokeWidth={1.75} />
                                }
                                onClick={() => setResetOpen(true)}
                            >
                                Сбросить двухфакторную аутентификацию
                            </Button>
                        )}
                    </Blueprint>
                )}
            </div>

            <ConfirmDialog
                open={resetOpen}
                onClose={() => setResetOpen(false)}
                loading={resetting}
                title="Сбросить двухфакторную аутентификацию?"
                body={`Коды из приложения ${user?.name ?? ''} перестанут работать, все его сеансы завершатся. Действие попадёт в журнал.`}
                confirmLabel="Сбросить"
                onConfirm={() => {
                    setResetting(true);
                    router.post(
                        UserController.resetTwoFactor.url(user!.id),
                        {},
                        {
                            preserveScroll: true,
                            onFinish: () => {
                                setResetting(false);
                                setResetOpen(false);
                            },
                        },
                    );
                }}
            />

            <div className="news-form-actions">
                <LinkButton href={UserController.index.url()} variant="ghost">
                    Отмена
                </LinkButton>
                <div style={{ flex: 1 }} />
                <Button
                    variant="primary"
                    icon={<Save size={15} strokeWidth={1.75} />}
                    loading={processing}
                    disabled={!can('users.create') && !can('users.edit')}
                    onClick={submit}
                >
                    Сохранить
                </Button>
            </div>
        </>
    );
}
