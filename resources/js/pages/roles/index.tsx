import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Pencil, Plus, ShieldCheck } from 'lucide-react';
import RoleController from '@/actions/App/Http/Controllers/Cms/RoleController';
import UserController from '@/actions/App/Http/Controllers/Cms/UserController';
import { plural } from '@/lib/plural';
import { MODULE_GROUPS, rightsSummary } from '@/lib/roles';
import type { RightsAction, RightsMatrix, RightsModule } from '@/lib/roles';
import { Tag } from '@/ui/Badge';
import { Blueprint } from '@/ui/Blueprint';
import { LinkButton } from '@/ui/Button';
import { PageHeader } from '@/ui/PageHeader';

interface Role {
    id: number;
    value: string;
    label: string;
    description: string | null;
    is_administrator: boolean;
    is_built_in: boolean;
    user_count: number;
    matrix: RightsMatrix;
    needs_two_factor: boolean;
}

interface Props {
    roles: Role[];
    modules: RightsModule[];
    actions: RightsAction[];
    can_manage: boolean;
}

export default function RolesIndex({
    roles,
    modules,
    actions,
    can_manage,
}: Props) {
    const moduleByValue = new Map(modules.map((m) => [m.value, m]));

    return (
        <>
            <Head title="Роли и права" />
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
                title="Роли и права"
                subtitle={
                    can_manage
                        ? 'Роль определяет, что сотрудник может делать в системе. Права ролей можно изменить, а для особых случаев — создать свою роль.'
                        : 'Что может каждая роль. Роли и права настраивает администратор.'
                }
                actions={
                    can_manage && (
                        <LinkButton
                            href={RoleController.create.url()}
                            variant="primary"
                            icon={<Plus size={16} strokeWidth={1.75} />}
                        >
                            Добавить роль
                        </LinkButton>
                    )
                }
            />

            <div
                style={{
                    display: 'grid',
                    gridTemplateColumns:
                        'repeat(auto-fill, minmax(260px, 1fr))',
                    gap: 12,
                    marginBottom: 16,
                }}
            >
                {roles.map((role) => (
                    <Blueprint
                        key={role.value}
                        style={{
                            padding: 16,
                            display: 'flex',
                            flexDirection: 'column',
                            gap: 8,
                        }}
                    >
                        <div
                            style={{
                                display: 'flex',
                                alignItems: 'center',
                                gap: 8,
                                flexWrap: 'wrap',
                            }}
                        >
                            <h3 className="ui-card-title" style={{ margin: 0 }}>
                                {role.label}
                            </h3>
                            {role.is_administrator ? (
                                <Tag tone="accent">Все права</Tag>
                            ) : (
                                role.is_built_in && (
                                    <Tag tone="neutral">Основная</Tag>
                                )
                            )}
                        </div>
                        {role.description && (
                            <p
                                style={{
                                    margin: 0,
                                    fontSize: 12.5,
                                    color: 'var(--color-neutral-600)',
                                }}
                            >
                                {role.description}
                            </p>
                        )}
                        {role.needs_two_factor && (
                            <span
                                style={{
                                    display: 'inline-flex',
                                    alignItems: 'center',
                                    gap: 5,
                                    fontSize: 11.5,
                                    color: 'var(--color-neutral-500)',
                                }}
                            >
                                <ShieldCheck size={13} strokeWidth={1.75} />
                                Вход с кодом из приложения
                            </span>
                        )}
                        <div
                            style={{
                                marginTop: 'auto',
                                paddingTop: 4,
                                display: 'flex',
                                alignItems: 'center',
                                justifyContent: 'space-between',
                                gap: 8,
                            }}
                        >
                            <Link
                                href={UserController.index.url({
                                    query: { role: role.value },
                                })}
                                style={{ fontSize: 12.5 }}
                            >
                                {role.user_count}{' '}
                                {plural(
                                    role.user_count,
                                    'сотрудник',
                                    'сотрудника',
                                    'сотрудников',
                                )}
                            </Link>
                            {can_manage && !role.is_administrator && (
                                <LinkButton
                                    href={RoleController.edit.url(role.id)}
                                    size="sm"
                                    icon={
                                        <Pencil size={14} strokeWidth={1.75} />
                                    }
                                >
                                    Изменить
                                    <span className="sr-only">
                                        {` роль «${role.label}»`}
                                    </span>
                                </LinkButton>
                            )}
                        </div>
                    </Blueprint>
                ))}
            </div>

            <Blueprint style={{ padding: 0, overflow: 'hidden' }}>
                <div
                    style={{
                        padding: '14px 18px',
                        borderBottom: '1px solid var(--color-divider)',
                    }}
                >
                    <h3 className="ui-card-title" style={{ margin: 0 }}>
                        Что может каждая роль
                    </h3>
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
                                <th scope="col" style={headCell}>
                                    Раздел
                                </th>
                                {roles.map((role) => (
                                    <th
                                        key={role.value}
                                        scope="col"
                                        style={headCell}
                                    >
                                        {role.label}
                                    </th>
                                ))}
                            </tr>
                        </thead>
                        {MODULE_GROUPS.map((group) => (
                            <tbody key={group.label}>
                                <tr>
                                    <th
                                        scope="colgroup"
                                        colSpan={roles.length + 1}
                                        style={groupCell}
                                    >
                                        {group.label}
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
                                            <th scope="row" style={rowCell}>
                                                {module.label}
                                            </th>
                                            {roles.map((role) => (
                                                <td
                                                    key={role.value}
                                                    style={bodyCell}
                                                >
                                                    {rightsSummary(
                                                        module,
                                                        role.matrix[value],
                                                        actions,
                                                    )}
                                                </td>
                                            ))}
                                        </tr>
                                    );
                                })}
                            </tbody>
                        ))}
                    </table>
                </div>
            </Blueprint>
        </>
    );
}

const headCell = {
    textAlign: 'left',
    padding: '10px 18px',
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
    padding: '9px 18px',
    fontWeight: 500,
    minWidth: 160,
} as const;

const bodyCell = {
    padding: '9px 18px',
    color: 'var(--color-neutral-700)',
    minWidth: 150,
} as const;
