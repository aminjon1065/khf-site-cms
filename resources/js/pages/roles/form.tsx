import { Head, Link, router, useForm } from '@inertiajs/react';
import { ArrowLeft, Lock, Save, ShieldCheck, Trash2 } from 'lucide-react';
import { useState } from 'react';
import RoleController from '@/actions/App/Http/Controllers/Cms/RoleController';
import { plural } from '@/lib/plural';
import { grantedRights, MODULE_GROUPS, toggleRight } from '@/lib/roles';
import type { RightsAction, RightsMatrix, RightsModule } from '@/lib/roles';
import { Blueprint } from '@/ui/Blueprint';
import { Button, LinkButton } from '@/ui/Button';
import {
    Checkbox,
    Field,
    Input,
    InputError,
    Select,
    Textarea,
} from '@/ui/Field';
import { ConfirmDialog } from '@/ui/Overlay';
import { PageHeader } from '@/ui/PageHeader';

interface RoleData {
    id: number;
    label: string;
    description: string | null;
    is_built_in: boolean;
    user_count: number;
    matrix: RightsMatrix;
}

interface Template {
    value: string;
    label: string;
    permissions: string[];
}

interface Props {
    role: RoleData | null;
    templates: Template[];
    modules: RightsModule[];
    actions: RightsAction[];
    administrator_only: string[];
    two_factor_rights: string[];
}

export default function RoleForm({
    role,
    templates,
    modules,
    actions,
    administrator_only,
    two_factor_rights,
}: Props) {
    const isEdit = role !== null;
    const [deleteOpen, setDeleteOpen] = useState(false);
    const [deleting, setDeleting] = useState(false);
    const moduleByValue = new Map(modules.map((m) => [m.value, m]));

    const form = useForm({
        label: role?.label ?? '',
        description: role?.description ?? '',
        permissions: role ? grantedRights(role.matrix) : ([] as string[]),
    });
    const { data, setData, processing, errors } = form;
    const errorOf = (key: string): string | undefined =>
        (errors as Record<string, string | undefined>)[key];
    const permissionsError =
        errorOf('permissions') ??
        Object.entries(errors).find(([key]) =>
            key.startsWith('permissions.'),
        )?.[1];

    const submit = () => {
        if (role) {
            form.put(RoleController.update.url(role.id), {
                preserveScroll: true,
            });
        } else {
            form.post(RoleController.store.url(), { preserveScroll: true });
        }
    };

    const setRight = (module: string, action: string, on: boolean) =>
        setData(
            'permissions',
            toggleRight(data.permissions, module, action, on),
        );

    const needsCode = data.permissions.some((p) =>
        two_factor_rights.includes(p),
    );
    // A critical alert goes out only from someone who may approve alerts
    // (WorkflowService::guardCriticalPublish).
    const releasesCriticalAlerts = data.permissions.includes('alerts.approve');

    return (
        <>
            <Head title={isEdit ? `Роль «${role.label}»` : 'Новая роль'} />

            <PageHeader
                eyebrow={
                    <Link
                        href={RoleController.index.url()}
                        style={{
                            display: 'inline-flex',
                            alignItems: 'center',
                            gap: 6,
                            color: 'var(--color-neutral-600)',
                            textDecoration: 'none',
                        }}
                    >
                        <ArrowLeft size={14} strokeWidth={1.75} /> Роли и права
                    </Link>
                }
                title={isEdit ? data.label || 'Роль' : 'Новая роль'}
                subtitle="Название роли и что могут сотрудники с ней."
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

            <Blueprint style={{ padding: 20, marginBottom: 16 }}>
                <h3 className="ui-card-title" style={{ marginTop: 0 }}>
                    Роль
                </h3>
                <Field label="Название" required error={errorOf('label')}>
                    <Input
                        value={data.label}
                        onChange={(e) => setData('label', e.target.value)}
                        hasError={!!errorOf('label')}
                        maxLength={60}
                        placeholder="Оператор предупреждений"
                    />
                </Field>
                <Field
                    label="Для кого эта роль"
                    hint="Коротко — увидят администратор и сотрудники, выбирая роль."
                    error={errorOf('description')}
                >
                    <Textarea
                        rows={2}
                        value={data.description}
                        onChange={(e) => setData('description', e.target.value)}
                        maxLength={255}
                        placeholder="Дежурные оперативной службы: выпускают предупреждения в любое время"
                    />
                </Field>
                {!isEdit && templates.length > 0 && (
                    <Field
                        label="Взять права из роли"
                        hint="Отметки ниже заменятся правами выбранной роли — их можно поправить."
                    >
                        <Select
                            value=""
                            options={[
                                { value: '', label: '— Не брать —' },
                                ...templates.map((t) => ({
                                    value: t.value,
                                    label: t.label,
                                })),
                            ]}
                            onChange={(e) => {
                                const template = templates.find(
                                    (t) => t.value === e.target.value,
                                );

                                if (template) {
                                    setData('permissions', [
                                        ...template.permissions,
                                    ]);
                                }
                            }}
                        />
                    </Field>
                )}
            </Blueprint>

            <Blueprint style={{ padding: 0, overflow: 'hidden' }}>
                <div
                    style={{
                        padding: '14px 18px',
                        borderBottom: '1px solid var(--color-divider)',
                    }}
                >
                    <h3 className="ui-card-title" style={{ margin: 0 }}>
                        Права
                    </h3>
                    <p
                        style={{
                            margin: '4px 0 0',
                            fontSize: 12.5,
                            color: 'var(--color-neutral-600)',
                        }}
                    >
                        Без «Просмотра» раздел не откроется, поэтому он
                        отмечается вместе с любым другим правом.
                    </p>
                    {permissionsError && (
                        <InputError message={permissionsError} />
                    )}
                </div>
                <div style={{ overflowX: 'auto' }}>
                    <table
                        style={{
                            width: '100%',
                            borderCollapse: 'collapse',
                            fontSize: 12.5,
                        }}
                    >
                        <thead>
                            <tr>
                                <th
                                    scope="col"
                                    style={{
                                        ...headCell,
                                        ...stickyColumn,
                                        textAlign: 'left',
                                    }}
                                >
                                    Раздел
                                </th>
                                {actions.map((a) => (
                                    <th
                                        key={a.value}
                                        scope="col"
                                        style={headCell}
                                    >
                                        {a.label}
                                    </th>
                                ))}
                            </tr>
                        </thead>
                        {MODULE_GROUPS.map((group) => (
                            <tbody key={group.label}>
                                <tr>
                                    <th
                                        scope="colgroup"
                                        colSpan={actions.length + 1}
                                        style={groupCell}
                                    >
                                        <span
                                            style={{
                                                position: 'sticky',
                                                left: 18,
                                            }}
                                        >
                                            {group.label}
                                        </span>
                                    </th>
                                </tr>
                                {group.modules.map((value) => {
                                    const module = moduleByValue.get(value);

                                    if (!module) {
                                        return null;
                                    }

                                    return (
                                        <tr
                                            key={value}
                                            style={{
                                                borderTop:
                                                    '1px solid var(--color-divider)',
                                            }}
                                        >
                                            <th
                                                scope="row"
                                                style={{
                                                    ...rowCell,
                                                    ...stickyColumn,
                                                }}
                                            >
                                                {module.label}
                                            </th>
                                            {actions.map((a) => {
                                                const name = `${value}.${a.value}`;

                                                if (
                                                    !module.actions.includes(
                                                        a.value,
                                                    )
                                                ) {
                                                    return (
                                                        <td
                                                            key={a.value}
                                                            style={cell}
                                                        />
                                                    );
                                                }

                                                if (
                                                    administrator_only.includes(
                                                        name,
                                                    )
                                                ) {
                                                    return (
                                                        <td
                                                            key={a.value}
                                                            style={cell}
                                                            title="Только администратор"
                                                        >
                                                            <Lock
                                                                size={14}
                                                                strokeWidth={
                                                                    1.75
                                                                }
                                                                color="var(--color-neutral-400)"
                                                                aria-hidden
                                                                style={{
                                                                    // Tailwind makes svg a block: centre it in the cell.
                                                                    margin: '0 auto',
                                                                }}
                                                            />
                                                            <span className="sr-only">
                                                                Только
                                                                администратор
                                                            </span>
                                                        </td>
                                                    );
                                                }

                                                return (
                                                    <td
                                                        key={a.value}
                                                        style={cell}
                                                    >
                                                        <Checkbox
                                                            aria-label={`${module.label}: ${a.label.toLowerCase()}`}
                                                            checked={data.permissions.includes(
                                                                name,
                                                            )}
                                                            onChange={(e) =>
                                                                setRight(
                                                                    value,
                                                                    a.value,
                                                                    e.target
                                                                        .checked,
                                                                )
                                                            }
                                                        />
                                                    </td>
                                                );
                                            })}
                                        </tr>
                                    );
                                })}
                            </tbody>
                        ))}
                    </table>
                </div>
                {(needsCode || releasesCriticalAlerts) && (
                    <div
                        style={{
                            padding: '12px 18px',
                            borderTop: '1px solid var(--color-divider)',
                            display: 'flex',
                            flexDirection: 'column',
                            gap: 6,
                            fontSize: 12.5,
                            color: 'var(--color-neutral-700)',
                        }}
                    >
                        {needsCode && (
                            <span
                                style={{
                                    display: 'inline-flex',
                                    alignItems: 'flex-start',
                                    gap: 6,
                                }}
                            >
                                <ShieldCheck
                                    size={15}
                                    strokeWidth={1.75}
                                    style={{ flexShrink: 0, marginTop: 1 }}
                                />
                                Роль может публиковать или согласовывать —
                                сотрудники с ней будут входить с кодом из
                                приложения (двухфакторная аутентификация).
                            </span>
                        )}
                        {releasesCriticalAlerts && (
                            <span>
                                Согласование предупреждений позволяет выпускать
                                и критические предупреждения.
                            </span>
                        )}
                    </div>
                )}
            </Blueprint>

            {isEdit && (
                <Blueprint style={{ padding: 20, marginTop: 16 }}>
                    <h3 className="ui-card-title" style={{ marginTop: 0 }}>
                        Удаление роли
                    </h3>
                    <p
                        style={{
                            margin: '0 0 12px',
                            fontSize: 13,
                            color: 'var(--color-neutral-600)',
                        }}
                    >
                        {role.user_count > 0
                            ? `У роли ${role.user_count} ${plural(role.user_count, 'сотрудник', 'сотрудника', 'сотрудников')}. Чтобы удалить её, сначала назначьте им другую роль.`
                            : 'У роли нет сотрудников — её можно удалить.'}
                    </p>
                    <Button
                        variant="danger-outline"
                        icon={<Trash2 size={15} strokeWidth={1.75} />}
                        disabled={role.user_count > 0}
                        onClick={() => setDeleteOpen(true)}
                    >
                        Удалить роль
                    </Button>
                </Blueprint>
            )}

            {isEdit && (
                <ConfirmDialog
                    open={deleteOpen}
                    onClose={() => setDeleteOpen(false)}
                    loading={deleting}
                    title={`Удалить роль «${role.label}»?`}
                    body="Удаление попадёт в журнал действий."
                    confirmLabel="Удалить"
                    onConfirm={() => {
                        setDeleting(true);
                        router.delete(RoleController.destroy.url(role.id), {
                            onFinish: () => {
                                setDeleting(false);
                                setDeleteOpen(false);
                            },
                        });
                    }}
                />
            )}

            <div className="news-form-actions">
                <LinkButton href={RoleController.index.url()} variant="ghost">
                    Отмена
                </LinkButton>
                <div style={{ flex: 1 }} />
                <Button
                    variant="primary"
                    icon={<Save size={15} strokeWidth={1.75} />}
                    loading={processing}
                    onClick={submit}
                >
                    Сохранить
                </Button>
            </div>
        </>
    );
}

const headCell = {
    textAlign: 'center',
    padding: '10px 8px',
    color: 'var(--color-neutral-600)',
    fontWeight: 600,
    whiteSpace: 'nowrap',
} as const;

const groupCell = {
    textAlign: 'left',
    padding: '14px 18px 6px',
    fontSize: 11,
    fontWeight: 600,
    textTransform: 'uppercase',
    letterSpacing: '0.04em',
    color: 'var(--color-neutral-500)',
} as const;

const rowCell = {
    textAlign: 'left',
    padding: '8px 18px',
    fontWeight: 500,
    minWidth: 170,
} as const;

const cell = {
    textAlign: 'center',
    padding: '8px',
} as const;

/**
 * On a phone the table scrolls sideways: the section names stay in view.
 */
const stickyColumn = {
    position: 'sticky',
    left: 0,
    zIndex: 1,
    background: 'var(--color-surface)',
} as const;
