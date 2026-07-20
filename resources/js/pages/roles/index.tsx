import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Check, MapPin, Minus } from 'lucide-react';
import { useState } from 'react';
import { Tag } from '@/ui/Badge';
import { Blueprint } from '@/ui/Blueprint';
import { PageHeader } from '@/ui/PageHeader';

interface Option {
    value: string;
    label: string;
}

interface Role {
    value: string;
    label: string;
    description: string;
    region_scoped: boolean;
    user_count: number;
    matrix: Record<string, Record<string, boolean>>;
}

interface Props {
    roles: Role[];
    modules: Option[];
    actions: Option[];
}

export default function RolesIndex({ roles, modules, actions }: Props) {
    const [active, setActive] = useState<string>(roles[0]?.value ?? '');
    const role = roles.find((r) => r.value === active) ?? roles[0];

    return (
        <>
            <Head title="Роли и права" />
            <PageHeader
                eyebrow={
                    <Link
                        href="/users"
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
                subtitle="Матрица прав доступа. Роли заданы в системе и не редактируются вручную."
            />

            <div
                className="cms-two-col"
                style={{
                    display: 'grid',
                    gridTemplateColumns: 'minmax(240px, 320px) 1fr',
                    gap: 16,
                    alignItems: 'start',
                }}
            >
                {/* Список ролей */}
                <div style={{ display: 'flex', flexDirection: 'column', gap: 8 }}>
                    {roles.map((r) => {
                        const selected = r.value === role?.value;

                        return (
                            <button
                                key={r.value}
                                type="button"
                                onClick={() => setActive(r.value)}
                                style={{
                                    textAlign: 'left',
                                    cursor: 'pointer',
                                    border: '1px solid var(--color-divider)',
                                    borderLeft: selected
                                        ? '3px solid var(--color-accent)'
                                        : '3px solid transparent',
                                    background: selected
                                        ? 'var(--color-neutral-100)'
                                        : 'var(--color-surface, transparent)',
                                    borderRadius: 8,
                                    padding: '10px 12px',
                                    font: 'inherit',
                                }}
                            >
                                <div
                                    style={{
                                        display: 'flex',
                                        alignItems: 'center',
                                        gap: 8,
                                        justifyContent: 'space-between',
                                    }}
                                >
                                    <span style={{ fontWeight: 600, fontSize: 13.5 }}>
                                        {r.label}
                                    </span>
                                    <span
                                        style={{
                                            fontSize: 11.5,
                                            color: 'var(--color-neutral-500)',
                                        }}
                                    >
                                        {r.user_count}
                                    </span>
                                </div>
                                <div
                                    style={{
                                        fontSize: 11.5,
                                        color: 'var(--color-neutral-500)',
                                        marginTop: 2,
                                    }}
                                >
                                    {r.description}
                                </div>
                                {r.region_scoped && (
                                    <span
                                        style={{
                                            display: 'inline-flex',
                                            alignItems: 'center',
                                            gap: 4,
                                            marginTop: 6,
                                            fontSize: 11,
                                            color: 'var(--color-accent-700)',
                                        }}
                                    >
                                        <MapPin size={12} strokeWidth={1.75} /> В пределах
                                        региона
                                    </span>
                                )}
                            </button>
                        );
                    })}
                </div>

                {/* Матрица выбранной роли */}
                <Blueprint style={{ padding: 0, overflow: 'hidden' }}>
                    <div
                        style={{
                            padding: '14px 18px',
                            borderBottom: '1px solid var(--color-divider)',
                            display: 'flex',
                            alignItems: 'center',
                            gap: 10,
                        }}
                    >
                        <h3 className="ui-card-title" style={{ margin: 0 }}>
                            {role?.label}
                        </h3>
                        <Tag tone="neutral">{role?.user_count} чел.</Tag>
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
                                        style={{
                                            textAlign: 'left',
                                            padding: '10px 18px',
                                            color: 'var(--color-neutral-600)',
                                            fontWeight: 600,
                                        }}
                                    >
                                        Раздел
                                    </th>
                                    {actions.map((a) => (
                                        <th
                                            key={a.value}
                                            style={{
                                                padding: '10px 8px',
                                                textAlign: 'center',
                                                color: 'var(--color-neutral-600)',
                                                fontWeight: 600,
                                                whiteSpace: 'nowrap',
                                            }}
                                        >
                                            {a.label}
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody>
                                {modules.map((m) => (
                                    <tr
                                        key={m.value}
                                        style={{
                                            borderTop: '1px solid var(--color-divider)',
                                        }}
                                    >
                                        <td
                                            style={{
                                                padding: '9px 18px',
                                                fontWeight: 500,
                                            }}
                                        >
                                            {m.label}
                                        </td>
                                        {actions.map((a) => {
                                            const on =
                                                role?.matrix[m.value]?.[a.value] ??
                                                false;

                                            return (
                                                <td
                                                    key={a.value}
                                                    style={{
                                                        padding: '9px 8px',
                                                        textAlign: 'center',
                                                    }}
                                                >
                                                    {on ? (
                                                        <Check
                                                            size={16}
                                                            strokeWidth={2}
                                                            color="var(--color-success-600, #067647)"
                                                            aria-label="Разрешено"
                                                        />
                                                    ) : (
                                                        <Minus
                                                            size={14}
                                                            strokeWidth={1.5}
                                                            color="var(--color-neutral-300)"
                                                            aria-label="Нет доступа"
                                                        />
                                                    )}
                                                </td>
                                            );
                                        })}
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </Blueprint>
            </div>
        </>
    );
}
