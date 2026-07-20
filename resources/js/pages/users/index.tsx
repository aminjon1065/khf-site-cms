import { Head, Link, router } from '@inertiajs/react';
import { KeyRound, MoreVertical, Pencil, Plus, ShieldCheck, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { useCan } from '@/lib/auth';
import { Tag } from '@/ui/Badge';
import { IconButton, LinkButton } from '@/ui/Button';
import { DataTable, Pagination } from '@/ui/DataTable';
import type { Column } from '@/ui/DataTable';
import { Select } from '@/ui/Field';
import { FilterBar, SearchInput } from '@/ui/Filters';
import { ConfirmDialog, Dropdown } from '@/ui/Overlay';
import { PageHeader } from '@/ui/PageHeader';

interface UserRow {
    id: number;
    name: string;
    email: string;
    initials: string;
    position: string | null;
    department: string | null;
    role: string | null;
    role_value: string | null;
    region: string | null;
    is_active: boolean;
    two_factor: boolean;
    last_login_at: string | null;
    is_self: boolean;
}

interface Option {
    value: string;
    label: string;
}

interface Props {
    users: UserRow[];
    meta: {
        from: number | null;
        to: number | null;
        total: number;
        per_page: number;
        prev: string | null;
        next: string | null;
    };
    filters: { search: string; role: string; status: string };
    options: { roles: Option[] };
}

function fmt(date: string | null): string {
    if (!date) {
        return '—';
    }

    return new Date(date).toLocaleString('ru-RU', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

export default function UsersIndex({ users, meta, filters, options }: Props) {
    const can = useCan();
    const [deleteTarget, setDeleteTarget] = useState<UserRow | null>(null);
    const [processing, setProcessing] = useState(false);

    const reload = (patch: Partial<Props['filters']>) => {
        router.get(
            '/users',
            { ...filters, ...patch },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const columns: Column<UserRow>[] = [
        {
            key: 'name',
            header: 'Сотрудник',
            render: (r) => (
                <div style={{ display: 'flex', alignItems: 'center', gap: 10, minWidth: 0 }}>
                    <span
                        aria-hidden
                        style={{
                            width: 34,
                            height: 34,
                            flex: 'none',
                            borderRadius: '50%',
                            background: 'var(--color-neutral-200)',
                            color: 'var(--color-neutral-700)',
                            display: 'flex',
                            alignItems: 'center',
                            justifyContent: 'center',
                            fontSize: 12,
                            fontWeight: 700,
                        }}
                    >
                        {r.initials}
                    </span>
                    <div style={{ minWidth: 0 }}>
                        {can('users.edit') ? (
                            <Link
                                href={`/users/${r.id}/edit`}
                                style={{ fontWeight: 600, color: 'var(--color-text)', textDecoration: 'none' }}
                            >
                                {r.name}
                            </Link>
                        ) : (
                            <span style={{ fontWeight: 600 }}>{r.name}</span>
                        )}
                        <div style={{ fontSize: 11.5, color: 'var(--color-neutral-500)' }}>
                            {r.email}
                            {r.position ? ` · ${r.position}` : ''}
                        </div>
                    </div>
                </div>
            ),
        },
        {
            key: 'role',
            header: 'Роль',
            width: 180,
            render: (r) => (
                <div style={{ display: 'flex', flexDirection: 'column', gap: 2 }}>
                    <Tag tone="accent">{r.role ?? '—'}</Tag>
                    {r.region && (
                        <span style={{ fontSize: 11, color: 'var(--color-neutral-500)' }}>
                            {r.region}
                        </span>
                    )}
                </div>
            ),
        },
        {
            key: 'status',
            header: 'Статус',
            width: 120,
            render: (r) => (
                <Tag tone={r.is_active ? 'ok' : 'neutral'}>
                    {r.is_active ? 'Активен' : 'Отключён'}
                </Tag>
            ),
        },
        {
            key: 'two_factor',
            header: '2FA',
            width: 70,
            render: (r) =>
                r.two_factor ? (
                    <ShieldCheck
                        size={17}
                        strokeWidth={1.75}
                        color="var(--color-success-600, #067647)"
                        aria-label="Включена"
                    />
                ) : (
                    <span style={{ color: 'var(--color-neutral-400)' }}>—</span>
                ),
        },
        {
            key: 'last_login',
            header: 'Последний вход',
            width: 160,
            render: (r) => (
                <span style={{ fontSize: 12, color: 'var(--color-neutral-600)' }}>
                    {fmt(r.last_login_at)}
                </span>
            ),
        },
        {
            key: 'actions',
            header: '',
            width: 44,
            align: 'right',
            render: (r) =>
                can('users.edit') || can('users.delete') ? (
                    <Dropdown
                        align="right"
                        trigger={({ toggle }) => (
                            <IconButton label="Действия" onClick={toggle} variant="ghost">
                                <MoreVertical size={17} strokeWidth={1.5} />
                            </IconButton>
                        )}
                        items={[
                            ...(can('users.edit')
                                ? [
                                      {
                                          label: 'Редактировать',
                                          icon: <Pencil size={15} strokeWidth={1.5} />,
                                          onSelect: () => router.visit(`/users/${r.id}/edit`),
                                      },
                                  ]
                                : []),
                            ...(can('users.delete') && !r.is_self
                                ? [
                                      { separator: true },
                                      {
                                          label: 'Удалить…',
                                          icon: <Trash2 size={15} strokeWidth={1.5} />,
                                          danger: true,
                                          onSelect: () => setDeleteTarget(r),
                                      },
                                  ]
                                : []),
                        ]}
                    />
                ) : null,
        },
    ];

    return (
        <>
            <Head title="Пользователи" />
            <PageHeader
                title="Пользователи"
                subtitle="Сотрудники Комитета с доступом к системе управления"
                actions={
                    <div style={{ display: 'flex', gap: 8 }}>
                        <LinkButton
                            href="/roles"
                            variant="secondary"
                            icon={<KeyRound size={15} strokeWidth={1.75} />}
                        >
                            Роли и права
                        </LinkButton>
                        {can('users.create') && (
                            <LinkButton
                                href="/users/create"
                                variant="primary"
                                icon={<Plus size={16} strokeWidth={2} />}
                            >
                                Добавить сотрудника
                            </LinkButton>
                        )}
                    </div>
                }
            />

            <FilterBar>
                <SearchInput
                    placeholder="Имя, e-mail или отдел…"
                    defaultValue={filters.search}
                    onChange={(e) => reload({ search: e.target.value })}
                />
                <Select
                    placeholder="Роль: все"
                    value={filters.role}
                    options={options.roles}
                    onChange={(e) => reload({ role: e.target.value })}
                    style={{ width: 'auto' }}
                />
                <Select
                    placeholder="Статус: все"
                    value={filters.status}
                    options={[
                        { value: 'active', label: 'Активные' },
                        { value: 'inactive', label: 'Отключённые' },
                    ]}
                    onChange={(e) => reload({ status: e.target.value })}
                    style={{ width: 'auto' }}
                />
                <span
                    style={{
                        marginLeft: 'auto',
                        fontSize: 12.5,
                        color: 'var(--color-neutral-600)',
                    }}
                >
                    {users.length} из {meta.total}
                </span>
            </FilterBar>

            <DataTable
                columns={columns}
                rows={users}
                rowKey={(r) => r.id}
                emptyTitle="Сотрудники не найдены"
                emptyHint="Измените фильтры или добавьте нового сотрудника."
            />

            <Pagination
                from={meta.from ?? 0}
                to={meta.to ?? 0}
                total={meta.total}
                perPage={meta.per_page}
                onPrev={
                    meta.prev
                        ? () => router.visit(meta.prev!, { preserveState: true, preserveScroll: true })
                        : undefined
                }
                onNext={
                    meta.next
                        ? () => router.visit(meta.next!, { preserveState: true, preserveScroll: true })
                        : undefined
                }
            />

            <ConfirmDialog
                open={!!deleteTarget}
                onClose={() => setDeleteTarget(null)}
                loading={processing}
                title="Удалить сотрудника?"
                body={
                    deleteTarget
                        ? `Учётная запись «${deleteTarget.name}» будет удалена без возможности восстановления.`
                        : ''
                }
                confirmLabel="Удалить"
                onConfirm={() => {
                    if (!deleteTarget) {
                        return;
                    }

                    setProcessing(true);
                    router.delete(`/users/${deleteTarget.id}`, {
                        preserveScroll: true,
                        onFinish: () => {
                            setProcessing(false);
                            setDeleteTarget(null);
                        },
                    });
                }}
            />
        </>
    );
}
